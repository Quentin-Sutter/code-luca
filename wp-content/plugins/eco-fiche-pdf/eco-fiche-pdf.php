<?php
/**
 * Plugin Name: Eco Fiche PDF
 * Description: Génération de fiches PDF (FPDI + TCPDF) depuis un gabarit InDesign + partage (lien, WhatsApp, email).
 * Version: 1.0.2
 * Author: Petit Chat & Toi
 */

if (!defined('ABSPATH')) exit;

/* ================== CONFIG À REMPLIR AVEC TES IDS ================== */
function ecofp_ids(){
  return array(
    // En-tête
    'header' => array(
      'company_name' => '158',
      'case_number'  => '156',
      'technician'   => '183',
      'city'         => '74',
      'zip'          => '159',
      'date'         => '76',
      'time'         => '77',
    ),

    // Z1..Z5 = répéteurs mono-ligne (container 'row' + ses champs)
    'Z1' => array('row'=>'79','eligible'=>'88','surface'=>'80','hplus'=>'81','hmoins'=>'82','puissance'=>'84','sol'=>'85','chauffage'=>'83','infos'=>'87'),
    'Z2' => array('row'=>'144','eligible'=>'152','surface'=>'145','hplus'=>'146','hmoins'=>'147','puissance'=>'149','sol'=>'150','chauffage'=>'148','infos'=>'151'),
    'Z3' => array('row'=>'134','eligible'=>'142','surface'=>'135','hplus'=>'136','hmoins'=>'137','puissance'=>'139','sol'=>'140','chauffage'=>'138','infos'=>'141'),
    'Z4' => array('row'=>'124','eligible'=>'132','surface'=>'125','hplus'=>'126','hmoins'=>'127','puissance'=>'129','sol'=>'130','chauffage'=>'128','infos'=>'131'),
    'Z5' => array('row'=>'114','eligible'=>'122','surface'=>'115','hplus'=>'116','hmoins'=>'117','puissance'=>'119','sol'=>'120','chauffage'=>'118','infos'=>'121'),

    // Z6 = répéteur multi-lignes
    'Z6' => array('row'=>'104','eligible'=>'112','surface'=>'105','hplus'=>'106','hmoins'=>'107','puissance'=>'109','sol'=>'110','chauffage'=>'108','infos'=>'111'),

    // (optionnel) PHOTOS = répéteur
    // file = ID d’attachement OU URL / caption = texte
    'PHOTOS' => array(
      'row'     => '101',
      'file'    => '86',
      'caption' => '99',
    ),
  );
}
/* ================================================================ */


/* ---- Chemins de base (uploads + plugin) ---- */
function ecofp_base_paths() {
  $up  = wp_upload_dir();
  $u   = trailingslashit($up['basedir']).'eco-fiche-pdf/';
  $url = trailingslashit($up['baseurl']).'eco-fiche-pdf/';
  wp_mkdir_p($u.'sorties/');
  return (object) array(
    'uploads_dir' => $u,
    'uploads_url' => $url,
    'template'    => $u.'template.pdf',
    'badge_ok'    => $u.'badges_OK.png',
    'badge_no'    => $u.'badges_NO.png',
    'out_dir'     => $u.'sorties/',
  );
}

/* ---- Libs (FPDI + TCPDF) ---- */
function ecofp_require_libs() {
  $base = plugin_dir_path(__FILE__).'lib/';
  $tcpdf = $base.'tcpdf/tcpdf.php';
  $fpdi  = $base.'fpdi/src/autoload.php';
  if (!is_readable($tcpdf)) throw new \Exception('TCPDF introuvable: '.$tcpdf);
  if (!is_readable($fpdi))  throw new \Exception('FPDI introuvable: '.$fpdi);
  require_once $tcpdf;
  require_once $fpdi; // FPDI v2 autoload
}

/* ---- Helpers typographiques & dessin ---- */
function ecofp_set_font($pdf, $style='', $pt=10){ $pdf->SetFont('dejavusans', $style, $pt); }

function ecofp_text($pdf, $x,$y,$w,$h,$txt,$align='L',$style='',$pt=9,$multi=false){
  ecofp_set_font($pdf, $style, $pt);
  $pdf->SetXY($x,$y);
  if($multi){
    $pdf->MultiCell($w, $h, $txt, 0, $align, false, 1, '', '', true, 0, false, true, $h);
  } else {
    $pdf->Cell($w, $h, $txt, 0, 0, $align);
  }
}

function ecofp_image_contain($pdf, $path, $x, $y, $w, $h){
  if(!is_readable($path)) return;
  $size = @getimagesize($path); if(!$size) return;
  $iw = $size[0]; $ih = $size[1];
  $ratio = min($w/$iw, $h/$ih);
  $pw = $iw*$ratio; $ph = $ih*$ratio;
  $ox = $x + ($w-$pw)/2; $oy = $y + ($h-$ph)/2;
  $pdf->Image($path, $ox, $oy, $pw, $ph, '', '', '', false, 300);
}

function ecofp_badge($pdf,$x,$y,$w,$h,$ok,$img_ok,$img_no){
  $src = $ok ? $img_ok : $img_no;
  if(is_readable($src)){
    $pdf->Image($src, $x, $y, $w, $h, '', '', '', false, 300);
  } else {
    $pdf->SetFillColor($ok?34:0, $ok?161:0, $ok?82:0);
    $pdf->Rect($x,$y,$w,$h,'F');
    ecofp_text($pdf, $x, $y+1.3, $w, $h-2.6, $ok?'ÉLIGIBLE':'NON ÉLIGIBLE', 'C', 'B', 9, false);
  }
}

/* ---- Helper : formatage milliers FR (47 853, 75 116, etc.) ---- */
function ecofp_format_thousands_fr( $value ) {
  // On ne touche à rien si c'est vide
  if ($value === '' || $value === null) {
    return $value;
  }

  // On normalise la valeur en conservant le séparateur décimal (virgule ou point)
  $normalized = preg_replace('/[^0-9,.-]/', '', (string) $value);
  $normalized = str_replace(',', '.', $normalized);

  // On supprime les séparateurs de milliers éventuels (ex.: 12.345,67)
  if (substr_count($normalized, '.') > 1) {
    $parts    = explode('.', $normalized);
    $decimal  = array_pop($parts);
    $normalized = implode('', $parts) . '.' . $decimal;
  }

  // Si ce n'est pas numérique après normalisation, on renvoie la valeur brute
  if (!is_numeric($normalized)) {
    return $value;
  }

  // Conversion en float puis formatage FR : espace insécable pour les milliers, virgule pour les décimales
  $float     = (float) $normalized;
  $formatted = number_format($float, 3, ',', "\xC2\xA0");

  // Suppression des décimales inutiles
  $formatted = rtrim($formatted, '0');
  $formatted = rtrim($formatted, ',');

  return $formatted;
}

/* ---- Helper : applique le formatage milliers aux champs numériques d'une zone ---- */
function ecofp_format_zone_numbers($bloc){
  foreach (array('surface','hplus','hmoins','puissance') as $k){
    if (isset($bloc[$k]) && $bloc[$k] !== '') {
      $bloc[$k] = ecofp_format_thousands_fr($bloc[$k]);
    }
  }
  return $bloc;
}

/* ---- Helper : formatage date en JJ/MM/AAAA ---- */
function ecofp_format_date_fr( $value ) {
  $value = trim((string) $value);
  if ($value === '') {
    return $value;
  }

  // Cas le plus probable : 2025-11-24 (YYYY-MM-DD)
  $dt = DateTime::createFromFormat('Y-m-d', $value);
  if ($dt instanceof DateTime) {
    return $dt->format('d/m/Y');
  }

  // Si jamais Formidable renvoie déjà du 24/11/2025, on ne touche pas
  $dt = DateTime::createFromFormat('d/m/Y', $value);
  if ($dt instanceof DateTime) {
    return $dt->format('d/m/Y');
  }

  // Sinon, on renvoie la valeur brute pour ne pas faire de bêtise
  return $value;
}


/* ---- Coordonnées (mm) ---- */
function ecofp_coords(){
  return array(
    'P1' => array(
      'header' => array(
        array('company_name', 104.647, 12.524, 92.829, 5.576, 'B', 11, 'R'),
        array('case_number',  104.647, 19.009, 92.829, 4.858, '',  9, 'R'),
        array('technician',   104.647, 24.928, 92.829, 4.858, '',  9, 'R'),
        array('city_zip',     104.647, 30.848, 92.829, 4.858, '',  9, 'R'),
        array('date_time',    104.647, 37.102, 92.829, 4.858, '',  9, 'R'),
      ),
      'zone1' => array(
        'badge'=>array(171.287,104.383,25.836,6.5),
        'surface'=>array(36.957,118.897,50.586,3.743),
        'hplus'=>array(36.957,126.383,50.586,3.743),
        'hmoins'=>array(36.957,133.868,50.586,3.743),
        'puissance'=>array(146.714,126.383,50.586,3.743),
        'sol'=>array(146.714,118.897,50.586,3.743),
        'chauffage'=>array(12.524,154.416,184.776,7.485,true),
        'infos'=>array(12.524,170.833,184.776,7.485,true),
      ),
      'zone2' => array(
        'badge'=>array(171.287,195.681,25.836,6.5),
        'surface'=>array(36.957,210.195,50.586,3.743),
        'hplus'=>array(36.957,217.681,50.586,3.743),
        'hmoins'=>array(36.957,225.166,50.586,3.743),
        'puissance'=>array(146.714,217.681,50.586,3.743),
        'sol'=>array(146.714,210.195,50.586,3.743),
        'chauffage'=>array(12.524,245.713,184.776,7.485,true),
        'infos'=>array(12.524,262.131,184.776,7.485,true),
      ),
    ),
    'PZ' => array(
      'A'=>array(
        'badge'=>array(171.287,14.7,25.836,6.5),
        'surface'=>array(36.957,29.215,50.586,3.743),
        'hplus'=>array(36.957,36.7,50.586,3.743),
        'hmoins'=>array(36.957,44.185,50.586,3.743),
        'puissance'=>array(146.714,36.7,50.586,3.743),
        'sol'=>array(146.714,29.215,50.586,3.743),
        'chauffage'=>array(12.524,64.733,184.776,7.485,true),
        'infos'=>array(12.524,81.151,184.776,7.485,true),
      ),
      'B'=>array(
        'badge'=>array(171.287,105.998,25.836,6.5),
        'surface'=>array(36.957,120.513,50.586,3.743),
        'hplus'=>array(36.957,127.998,50.586,3.743),
        'hmoins'=>array(36.957,135.483,50.586,3.743),
        'puissance'=>array(146.714,127.998,50.586,3.743),
        'sol'=>array(146.714,120.513,50.586,3.743),
        'chauffage'=>array(12.524,156.031,184.776,7.485,true),
        'infos'=>array(12.524,172.449,184.776,7.485,true),
      ),
      'C'=>array(
        'badge'=>array(171.287,195.801,25.836,6.5),
        'surface'=>array(36.957,210.316,50.586,3.743),
        'hplus'=>array(36.957,217.801,50.586,3.743),
        'hmoins'=>array(36.957,225.287,50.586,3.743),
        'puissance'=>array(146.714,217.801,50.586,3.743),
        'sol'=>array(146.714,210.316,50.586,3.743),
        'chauffage'=>array(12.524,245.834,184.776,7.485,true),
        'infos'=>array(12.524,262.252,184.776,7.485,true),
      ),
    ),
    'PH'=>array('x0'=>12.7,'y0'=>47.15, 'cols'=>4, 'boxW'=>43.15,'boxH'=>64.9,'gapX'=>4.0,'gapY'=>4.0,'capH'=>4.505),
  );
}

/* ---- Map d'une entrée Formidable → données PDF ---- */
function ecofp_map_entry_to_data($entry_id){
  if (!class_exists('FrmEntry')) return new WP_Error('frm', 'Formidable introuvable');
  $e = FrmEntry::getOne($entry_id, true);
  if(!$e) return new WP_Error('entry','Entrée inconnue');
  $m = $e->metas;
  $ID = ecofp_ids();

  $get = function($key) use ($m){ return isset($m[$key]) ? $m[$key] : ''; };
  $trueish = function($v){
    $v = is_array($v) ? reset($v) : $v;
    $v = mb_strtolower(trim((string)$v));
    return in_array($v, array('1','oui','yes','true','éligible','eligible'), true);
  };

  // En-tête
  $company  = $get($ID['header']['company_name']);

  // N° de dossier → toujours en MAJUSCULES (mais sans formatage milliers)
  $case     = $get($ID['header']['case_number']);
  if ($case !== '') {
    if (function_exists('mb_strtoupper')) {
      $case = mb_strtoupper($case, 'UTF-8');
    } else {
      $case = strtoupper($case);
    }
  }
  $case_fmt = $case ? ('N° '.$case) : '';

  $tech     = $get($ID['header']['technician']);
  $city     = $get($ID['header']['city']);


  // ⬇️ ICI : on récupère la valeur brute puis on la formate
  $zip_raw  = $get($ID['header']['zip']);
  $zip      = ecofp_format_thousands_fr( $zip_raw );

  // Date + heure : on reformate la date en JJ/MM/AAAA pour le PDF
  $date_raw = $get($ID['header']['date']);
  $date     = ecofp_format_date_fr( $date_raw );
  $time     = $get($ID['header']['time']);



  // Répéteurs
  $childCache = array();
  $getChildMetas = function($rk) use (&$childCache){
    if (!$rk) return array();
    if (!isset($childCache[$rk])) {
      $child = class_exists('FrmEntry') ? FrmEntry::getOne((int)$rk, true) : null;
      $childCache[$rk] = ($child && !empty($child->metas)) ? $child->metas : array();
    }
    return $childCache[$rk];
  };
  $pullChild = function($rk, $fid) use ($getChildMetas){
    $cm = $getChildMetas($rk);
    if (!isset($cm[$fid])) return '';
    $v = $cm[$fid];
    return is_array($v) ? reset($v) : $v;
  };

  $zones = array();
  foreach (array('Z1','Z2','Z3','Z4','Z5') as $Z){
    if (empty($ID[$Z]['row'])) continue;
    $rowIds = (isset($m[$ID[$Z]['row']]) && is_array($m[$ID[$Z]['row']])) ? array_values($m[$ID[$Z]['row']]) : array();
    if (empty($rowIds)) continue;
    $rk = $rowIds[0];
    $bloc = array(
      'eligible'  => $trueish( $pullChild($rk, $ID[$Z]['eligible']) ),
      'surface'   => $pullChild($rk, $ID[$Z]['surface']),
      'hplus'     => $pullChild($rk, $ID[$Z]['hplus']),
      'hmoins'    => $pullChild($rk, $ID[$Z]['hmoins']),
      'puissance' => $pullChild($rk, $ID[$Z]['puissance']),
      'sol'       => $pullChild($rk, $ID[$Z]['sol']),
      'chauffage' => $pullChild($rk, $ID[$Z]['chauffage']),
      'infos'     => $pullChild($rk, $ID[$Z]['infos']),
    );

    // 👉 On formate les nombres (surface, hauteurs, puissance)
    $bloc = ecofp_format_zone_numbers($bloc);

    if (trim(implode('', $bloc)) !== '') $zones[] = $bloc;

  }

  if (!empty($ID['Z6']['row'])) {
    $rows = (isset($m[$ID['Z6']['row']]) && is_array($m[$ID['Z6']['row']])) ? array_values($m[$ID['Z6']['row']]) : array();
    foreach ($rows as $rk){
      $bloc = array(
        'eligible'  => $trueish( $pullChild($rk, $ID['Z6']['eligible']) ),
        'surface'   => $pullChild($rk, $ID['Z6']['surface']),
        'hplus'     => $pullChild($rk, $ID['Z6']['hplus']),
        'hmoins'    => $pullChild($rk, $ID['Z6']['hmoins']),
        'puissance' => $pullChild($rk, $ID['Z6']['puissance']),
        'sol'       => $pullChild($rk, $ID['Z6']['sol']),
        'chauffage' => $pullChild($rk, $ID['Z6']['chauffage']),
        'infos'     => $pullChild($rk, $ID['Z6']['infos']),
      );

      // 👉 même formatage des nombres pour les lignes Z6
      $bloc = ecofp_format_zone_numbers($bloc);

      $zones[] = $bloc;
    }
  }


  return array(
    'company_name' => $company ?: 'NOM DE L’ENTREPRISE',
    'case_number'  => $case_fmt ?: 'N° DE DOSSIER',
    'technician'   => $tech    ?: 'Prénom Nom',
    'city_zip'     => trim(($city ?: 'Ville').' - '.($zip ?: 'Code postal')),
    'date_time'    => trim(($date ?: 'DATE').' - '.($time ?: 'HEURE')),
    'zones'        => $zones,
  );
}

/* ---- URL (public) → chemin disque local (lisible par TCPDF) ---- */
function ecofp_url_to_path($url){
  if (!$url) return '';
  // Déjà un chemin local ?
  if (strpos($url, DIRECTORY_SEPARATOR) === 0 || preg_match('~^[A-Z]:\\\\~i', $url)) {
    return is_readable($url) ? $url : '';
  }
  $up = wp_upload_dir();
  $baseurl  = rtrim($up['baseurl'], '/');
  $basedir  = rtrim($up['basedir'], DIRECTORY_SEPARATOR);

  // 1) correspondance stricte baseurl → basedir
  if (strpos($url, $baseurl) === 0) {
    $path = $basedir . str_replace($baseurl, '', $url);
    return is_readable($path) ? $path : '';
  }
  // 2) domaine différent (CDN/www) : on cherche /wp-content/uploads/
  $p = wp_parse_url($url);
  if (!empty($p['path']) && ($pos = strpos($p['path'], '/wp-content/uploads/')) !== false) {
    $suffix = substr($p['path'], $pos + strlen('/wp-content/uploads/'));
    $path   = $basedir . DIRECTORY_SEPARATOR . $suffix;
    if (is_readable($path)) return $path;
  }
  // 3) dernier recours
  return is_readable($url) ? $url : '';
}

/* ---- Récupération des items photo depuis le répéteur ---- */
function ecofp_get_photo_items($entry_metas, $IDs){
  if (empty($IDs['PHOTOS']['row'])) return array();
  $rowField = $IDs['PHOTOS']['row'];
  $fileF    = $IDs['PHOTOS']['file'];
  $capF     = $IDs['PHOTOS']['caption'];

  $rows = (isset($entry_metas[$rowField]) && is_array($entry_metas[$rowField])) ? array_values($entry_metas[$rowField]) : array();
  $items = array();

  foreach ($rows as $rk){
    $child = class_exists('FrmEntry') ? FrmEntry::getOne((int)$rk, true) : null;
    $cm = ($child && !empty($child->metas)) ? $child->metas : array();

    $fileVal = isset($cm[$fileF]) ? $cm[$fileF] : '';
    if (is_array($fileVal)) $fileVal = reset($fileVal);

    // Résolution ID d’attachement → chemin disque, sinon URL → chemin
    if (is_numeric($fileVal)) {
      $att_id = (int)$fileVal;
      $path = function_exists('wp_get_original_image_path')
        ? wp_get_original_image_path($att_id)
        : get_attached_file($att_id);
      if (!$path || !is_readable($path)) {
        $url  = wp_get_attachment_url($att_id);
        $path = ecofp_url_to_path($url);
      }
    } else {
      $url  = (string)$fileVal;
      $path = ecofp_url_to_path($url);
    }

    $caption = isset($cm[$capF]) ? $cm[$capF] : '';
    if (is_array($caption)) $caption = reset($caption);

    if ($path && is_readable($path)) {
      $items[] = array('path'=>$path, 'caption'=>trim((string)$caption));
    }
  }
  return $items;
}

/* ---- Génération PDF -> renvoie (path, url) ---- */
function ecofp_generate_pdf($entry_id){
  ecofp_require_libs();
  $p = ecofp_base_paths();
  $C = ecofp_coords();

  if(!is_readable($p->template)) return new WP_Error('tpl','template.pdf manquant dans /uploads/eco-fiche-pdf/');
  $data = ecofp_map_entry_to_data($entry_id);
  if (is_wp_error($data)) return $data;

  // On lit les metas au complet pour PHOTOS
  $entry_full  = class_exists('FrmEntry') ? FrmEntry::getOne((int)$entry_id, true) : null;
  $entry_metas = ($entry_full && !empty($entry_full->metas)) ? $entry_full->metas : array();
  $IDs         = ecofp_ids();
  $photoItems  = ecofp_get_photo_items($entry_metas, $IDs);

  // FPDI + TCPDF
  $pdf = new setasign\Fpdi\Tcpdf\Fpdi('P','mm','A4', true, 'UTF-8', false);
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  $pdf->SetAutoPageBreak(false);

  // Template(s)
  $pdf->setSourceFile($p->template);
  $tpl1 = $pdf->importPage(1);
$tpl2 = $pdf->importPage(2);

$tpl3 = null;
try { $tpl3 = $pdf->importPage(3); } catch (\Throwable $e) { $tpl3 = $tpl2; }

$tpl4 = null;
try { $tpl4 = $pdf->importPage(4); } catch (\Throwable $e) { $tpl4 = $tpl3; }


  /* --- Page 1 --- */
  $pdf->AddPage();
  $pdf->useTemplate($tpl1, 0, 0, 210, 297);

  foreach($C['P1']['header'] as $h){
    list($key,$x,$y,$w,$hh,$style,$pt,$align) = $h;
    $txt = isset($data[$key]) ? $data[$key] : '';
    ecofp_text($pdf, $x,$y,$w,$hh, $txt, $align, $style, $pt, false);
  }

  // zones 1 & 2
  $zones = $data['zones'];
  $max = min(2, is_array($zones) ? count($zones) : 0);
  for($i=0; $i<$max; $i++){
    $cfg = $C['P1']['zone'.($i+1)];
    $z   = $zones[$i];

    ecofp_text($pdf, $cfg['surface'][0]-24, $cfg['surface'][1]-14, 30, 5, 'ZONE '.($i+1), 'L', 'B', 11, false);
    ecofp_badge($pdf, $cfg['badge'][0], $cfg['badge'][1], $cfg['badge'][2], $cfg['badge'][3],
      !empty($z['eligible']), $p->badge_ok, $p->badge_no);

    // On n'écrit que les valeurs : les intitulés sont déjà sur le gabarit InDesign
    ecofp_text($pdf, $cfg['surface'][0],  $cfg['surface'][1],  $cfg['surface'][2],  $cfg['surface'][3],  ($z['surface']   ?? ''));
    ecofp_text($pdf, $cfg['hplus'][0],    $cfg['hplus'][1],    $cfg['hplus'][2],    $cfg['hplus'][3],    ($z['hplus']     ?? ''));
    ecofp_text($pdf, $cfg['hmoins'][0],   $cfg['hmoins'][1],   $cfg['hmoins'][2],   $cfg['hmoins'][3],   ($z['hmoins']    ?? ''));
    ecofp_text($pdf, $cfg['puissance'][0],$cfg['puissance'][1],$cfg['puissance'][2],$cfg['puissance'][3],($z['puissance'] ?? ''));
    ecofp_text($pdf, $cfg['sol'][0],      $cfg['sol'][1],      $cfg['sol'][2],      $cfg['sol'][3],      ($z['sol']       ?? ''));
    ecofp_text($pdf, $cfg['chauffage'][0],$cfg['chauffage'][1],$cfg['chauffage'][2],$cfg['chauffage'][3],($z['chauffage'] ?? ''), 'L','',9,true);
    // "Informations supplémentaires" : on laisse uniquement le contenu, c'était déjà bon
    ecofp_text($pdf, $cfg['infos'][0],    $cfg['infos'][1],    $cfg['infos'][2],    $cfg['infos'][3],    ($z['infos']     ?? ''), 'L','',9,true);

  }

  // si 1 seule zone sur P1, on blanchit la zone2
  if (is_array($zones) && count($zones) === 1) {
    ecofp_clear_zone($pdf, $C['P1']['zone2']);
  }

  /* --- Pages suivantes (3 zones/page) --- */
  $idx = 2;
  while ($idx < (is_array($zones) ? count($zones) : 0)) {
    $remain = count($zones) - $idx;
    $pdf->AddPage();
    $pdf->useTemplate($tpl2, 0, 0, 210, 297);

    if ($remain == 1) {
      ecofp_clear_zone($pdf, $C['PZ']['B']);
      ecofp_clear_zone($pdf, $C['PZ']['C']);
    } elseif ($remain == 2) {
      ecofp_clear_zone($pdf, $C['PZ']['C']);
    }

    foreach (array('A','B','C') as $slot) {
      if ($idx >= count($zones)) break;
      $cfg = $C['PZ'][$slot];
      $z   = $zones[$idx];

      ecofp_text($pdf, $cfg['surface'][0]-24, $cfg['surface'][1]-14, 30, 5, 'ZONE '.($idx+1), 'L', 'B', 11, false);
      ecofp_badge($pdf, $cfg['badge'][0],$cfg['badge'][1],$cfg['badge'][2],$cfg['badge'][3], !empty($z['eligible']), $p->badge_ok, $p->badge_no);

      // Là aussi : uniquement les valeurs, les intitulés sont déjà dans le gabarit
      ecofp_text($pdf, $cfg['surface'][0],  $cfg['surface'][1],  $cfg['surface'][2],  $cfg['surface'][3],  ($z['surface']   ?? ''));
      ecofp_text($pdf, $cfg['hplus'][0],    $cfg['hplus'][1],    $cfg['hplus'][2],    $cfg['hplus'][3],    ($z['hplus']     ?? ''));
      ecofp_text($pdf, $cfg['hmoins'][0],   $cfg['hmoins'][1],   $cfg['hmoins'][2],   $cfg['hmoins'][3],   ($z['hmoins']    ?? ''));
      ecofp_text($pdf, $cfg['puissance'][0],$cfg['puissance'][1],$cfg['puissance'][2],$cfg['puissance'][3],($z['puissance'] ?? ''));
      ecofp_text($pdf, $cfg['sol'][0],      $cfg['sol'][1],      $cfg['sol'][2],      $cfg['sol'][3],      ($z['sol']       ?? ''));
      ecofp_text($pdf, $cfg['chauffage'][0],$cfg['chauffage'][1],$cfg['chauffage'][2],$cfg['chauffage'][3],($z['chauffage'] ?? ''),'L','',9,true);
      ecofp_text($pdf, $cfg['infos'][0],    $cfg['infos'][1],    $cfg['infos'][2],    $cfg['infos'][3],    ($z['infos']     ?? ''),'L','',9,true);


      $idx++;
    }
  }
  
  

/* --- PHOTOS (si présentes) --- */
if (!empty($photoItems)) {
  $pages = ecofp_paginate_photos($photoItems); // découpe en pages
  $photoPageIndex = 0;

  foreach ($pages as $pp) {
    $pdf->AddPage();

    if ($photoPageIndex === 0) {
      // 1ʳᵉ page photos : fond avec bandeau "PHOTOS DU SITE"
      if ($tpl4) {
        $pdf->useTemplate($tpl4, 0, 0, 210, 297);
      } elseif ($tpl3) {
        $pdf->useTemplate($tpl3, 0, 0, 210, 297);
      } else {
        $pdf->useTemplate($tpl2, 0, 0, 210, 297);
      }
    } else {
      // Pages photos suivantes : gabarit vierge (page 3), sinon page 2
      if ($tpl3) {
        $pdf->useTemplate($tpl3, 0, 0, 210, 297);
      } elseif ($tpl2) {
        $pdf->useTemplate($tpl2, 0, 0, 210, 297);
      }
      // pas de tpl4 ici → pas de doublon "PHOTOS DU SITE"
    }

    // Grille photos : toujours la même
    ecofp_draw_photos($pdf, $C['PH'], $pp);
    $photoPageIndex++;
  }
}



  /* --- Sortie --- */

  // On essaie de récupérer le nom brut de l'entreprise à partir des metas
  $company_slug = '';
  if (!empty($IDs['header']['company_name'])) {
    $company_field_id = $IDs['header']['company_name'];

    if (!empty($entry_metas[$company_field_id])) {
      $raw_name = $entry_metas[$company_field_id];

      // Si Formidable renvoie un tableau, on prend la première valeur
      if (is_array($raw_name)) {
        $raw_name = reset($raw_name);
      }

      // On transforme "H.S Énergie & Fils" -> "hs-energie-fils"
      $company_slug = sanitize_title($raw_name);
    }
  }

  // Construction du nom de fichier
  // Exemple : eco-environnement-fiche-390-20251114-184947.pdf
  $date_part = date('Ymd-His');

  if ($company_slug) {
    $fname = $company_slug . '-fiche-' . $entry_id . '-' . $date_part . '.pdf';
  } else {
    // fallback si pas de nom d'entreprise
    $fname = 'fiche-' . $entry_id . '-' . $date_part . '.pdf';
  }

  $path = $p->out_dir . $fname;
  $url  = $p->uploads_url . 'sorties/' . $fname;

  $pdf->Output($path, 'F');

  return (object) array('path' => $path, 'url' => $url);
}


/* --- Grille photos --- */
function ecofp_draw_photos($pdf, $PH, $items){
  $x0=$PH['x0']; $y0=$PH['y0']; $cols=$PH['cols']; $boxW=$PH['boxW']; $boxH=$PH['boxH']; $gapX=$PH['gapX']; $gapY=$PH['gapY']; $capH=$PH['capH'];
  $col=0; $row=0;
  foreach($items as $it){
    $path = isset($it['path']) ? $it['path'] : '';
    $legend = isset($it['caption']) ? $it['caption'] : '';
    $size = @getimagesize($path); $iw = $size ? $size[0] : 0; $ih = $size ? $size[1] : 0;
    $isL = ($iw && $ih) ? ($iw>$ih) : false;
    $span = $isL ? 2 : 1;
    $w = $span*$boxW + ($span-1)*$gapX; $h = $boxH;
    if($col + $span > $cols){ $col=0; $row++; }
    $x = $x0 + $col*($boxW+$gapX); $y = $y0 + $row*($boxH+$capH+$gapY);
    ecofp_image_contain($pdf, $path, $x,$y,$w,$h);
    ecofp_set_font($pdf,'',8.5);
    $pdf->SetXY($x, $y+$h+1);
    $pdf->MultiCell($w, $capH-1, $legend, 0, 'L', false, 1);
    $col += $span;
    if($col >= $cols){ $col=0; $row++; }
  }
}

/* --- Pagination des photos (8 "cases", paysage=2, portrait=1) --- */
function ecofp_paginate_photos($items){
  $SLOTS_PER_PAGE = 8; // 2 lignes × 4 colonnes

  $pages = array(); $page = array(); $slots = 0;
  foreach ($items as $it) {
    $iw = $ih = 0; $size = @getimagesize($it['path']);
    if ($size) { $iw = $size[0]; $ih = $size[1]; }

    // paysage = 2 “cases”, portrait = 1
    $span = ($iw && $ih && $iw > $ih) ? 2 : 1;

    // si ça ne rentre plus dans la page courante, on passe à la suivante
    if ($slots + $span > $SLOTS_PER_PAGE) {
      if (!empty($page)) $pages[] = $page;
      $page = array();
      $slots = 0;
    }

    $page[] = array_merge($it, array('span'=>$span));
    $slots += $span;
  }

  if (!empty($page)) $pages[] = $page;
  return $pages;
}


/* --- Outils d’UI --- */
add_shortcode('eco_fiche_pdf', function($atts){
  $a = shortcode_atts(array('entry'=>0), $atts);
  $entry = (int)$a['entry']; if(!$entry) return '<em>Entry manquante.</em>';
  $res = ecofp_generate_pdf($entry);
  if(is_wp_error($res)) return '<em>Erreur PDF : '.esc_html($res->get_error_message()).'</em>';

  $url = esc_url($res->url);
  $wa  = 'https://api.whatsapp.com/send?text='.rawurlencode('Fiche technique : '.$url);
  $mail= 'mailto:?subject='.rawurlencode('Fiche technique').'&body='.rawurlencode('Voici la fiche : '.$url);

  ob_start(); ?>
  <div class="eco-fiche-actions" style="display:flex;flex-wrap:wrap;gap:8px">
    <a class="button" href="<?php echo $url; ?>" target="_blank" rel="noopener">📄 Télécharger le PDF</a>
    <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo $url; ?>')">🔗 Copier le lien</button>
    <a class="button" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener">🟢 Partager WhatsApp</a>
    <a class="button" href="<?php echo esc_url($mail); ?>">✉️ Envoyer par e-mail</a>
  </div>
  <?php return ob_get_clean();
});

/* --- White-out d'une zone (efface en blanc la zone du fond) --- */
function ecofp_clear_zone($pdf, $cfg, $marginX=6, $marginY=8){
  $boxes = array($cfg['surface'],$cfg['hplus'],$cfg['hmoins'],$cfg['puissance'],$cfg['sol'],$cfg['chauffage'],$cfg['infos']);
  $x1=$y1=1e9; $x2=$y2=-1e9;
  foreach($boxes as $b){
    $x=$b[0]; $y=$b[1]; $w=$b[2]; $h=$b[3];
    if($x<$x1) $x1=$x; if($y<$y1) $y1=$y;
    if($x+$w>$x2) $x2=$x+$w; if($y+$h>$y2) $y2=$y+$h;
  }
  $pdf->SetFillColor(255,255,255);
  $pdf->Rect($x1-$marginX, $y1-14, ($x2-$x1)+2*$marginX, ($y2-$y1)+$marginY+14, 'F');
}

// === Route front: /?eco-pdf=ENTRY_ID ===
add_filter('query_vars', function($vars){
  $vars[] = 'eco-pdf';
  return $vars;
});

add_action('template_redirect', function(){
  $entry_id = get_query_var('eco-pdf');
  if (!$entry_id) return;

  $res = ecofp_generate_pdf((int)$entry_id);
  if (is_wp_error($res)) {
    wp_die('Erreur PDF : '.$res->get_error_message());
  }
  if (!file_exists($res->path)) {
    wp_die('PDF introuvable.');
  }

  // On laisse Safari / iOS gérer l’affichage du PDF
  wp_redirect( $res->url );
  exit;
});


/* --- Génération auto à la soumission (OPTIONNEL) --- */
add_action('frm_after_create_entry', function($entry_id,$form_id){
  if((int)$form_id !== 8) return; // <- remplace par l’ID de ton formulaire
  $res = ecofp_generate_pdf($entry_id);
  if(!is_wp_error($res) && class_exists('FrmEntryMeta')) {
    FrmEntryMeta::add_entry_meta($entry_id, 'pdf_url', null, $res->url);
  }
},10,2);

/* --- Debug metas --- */
add_shortcode('eco_fiche_debug', function($atts){
  if(!class_exists('FrmEntry')) return 'Formidable manquant.';
  $a = shortcode_atts(array('entry'=>0), $atts);
  $e = FrmEntry::getOne((int)$a['entry'], true);
  if(!$e) return 'Entrée inconnue.';
  ob_start(); echo '<pre>'; print_r($e->metas); echo '</pre>'; return ob_get_clean();
});
