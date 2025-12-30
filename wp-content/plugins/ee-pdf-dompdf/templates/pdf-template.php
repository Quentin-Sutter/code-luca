<?php /* @var string $title @var string $logo @var string $body @var string $footer */ ?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars($title) ?></title>
<style>
  /* Base */
  *{ box-sizing:border-box; }
  body{ font-family: Helvetica, Arial, "DejaVu Sans", sans-serif; color:#111; font-size:11pt; margin:0; }
  @page{ margin: 16mm; }

  /* Couleurs */
  .green{ color:#22a152; }
  .muted{ color:#444; }

  /* On garde l’en-tête de TA VIEW et on le remet en forme pour Dompdf (floats) */
  .fiche-header:after{ content:""; display:block; clear:both; }
  .fiche-header .fiche-meta{ float:left; width:70%; font-size:10.5pt; line-height:1.45; }
  .fiche-header img{ float:right; height:12mm; width:auto; }
  .fiche-title{ text-align:center; font-weight:800; font-size:20pt; margin:8mm 0 8mm; }
  .fiche-sep{ border:0; border-top:0.3pt solid #e5e7eb; margin:6mm 0 8mm; }

  /* Titres de zones / statut (on évite la grid) */
  .zone-head:after{ content:""; display:block; clear:both; margin-bottom:2mm; }
  .zone-left{ float:left; font-weight:800; letter-spacing:.02em; color:#22a152; }
  .zone-right{ float:right; font-weight:800; text-transform:uppercase; }

  /* .kv2 → 2 colonnes avec tableaux (compat Dompdf) */
  .kv2:after{ content:""; display:block; clear:both; }
  .kv2 .col{ float:left; width:48%; margin-right:4%; }
  .kv2 .col:last-child{ margin-right:0; }
  .kv2 table{ width:100%; border-collapse:collapse; }
  .kv2 td{ padding:1.5mm 0; vertical-align:top; }
  .kv2 td.k{ width:52%; font-weight:700; color:#1f2937; }
  .kv2 td.v{ width:48%; }

  /* Texte pleine largeur + séparateurs entre zones */
  .zone-text{ margin:4mm 0; }
  .zone + .zone{ border-top:0.3pt solid #e5e7eb; margin-top:8mm; padding-top:8mm; }

  /* --- PHOTOS (PDF) : 4 colonnes, vignettes même hauteur, jamais rognées --- */

/* Grille 4 colonnes propre */
.ft-grid{
  border-collapse: separate;      /* au lieu de collapse */
  table-layout: fixed;
  width: 100%;
  border-spacing: 4mm 6mm;        /* colonnes = 4mm, lignes = 6mm */
}
.ft-grid td{ width:25%; vertical-align: top; padding:0; } /* plus de padding sur td */

/* Filet anti-surprises CMS */
.ft-grid td, .ft-grid td * {
  padding: 0 !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
}


/* Figure neutre */
figure.ft-photo{ margin:0 !important; padding:0 !important; }

/* Boîte FIXE pour l’image (isole de la légende/paddings) */
.ft-photo-box{
  display:block !important;
  height:100mm !important;       /* ← ta hauteur unique */
  overflow:hidden !important;     /* coupe toute anomalie */
}

/* L’image en fond: occupe 100% de la boîte */
.ft-photo-img{
  display:block !important;
  width:100% !important;
  height:100% !important;         /* ← 100% de la boîte, donc 100mm */
  background-position:center center !important;
  background-repeat:no-repeat !important;
  background-size:contain !important;
  background-color:#fff !important;
}

/* Légende homogène */
.ft-caption{
  margin-top:2mm !important;
  font-size:9.5pt; color:#555; min-height:8mm;
}
.ft-caption:empty{ display:none; min-height:0; }



/* 5) Évite toute coupure dans une ligne de 4 */
.ft-grid tr{ page-break-inside: avoid; }


  /* Évite les coupures moches */
  .zone, .kv2, .fiche-header{ page-break-inside:avoid; }

/* cache tout ce qui est action/CTA dans la View quand on génère le PDF */
.fiche-actions,
a[href*="ee-pdf="],
a[href*="/modifier-une-fiche"],
a[href*="frm_entries_destroy"],
a[href*="/ajouter-des-photos/"],
.pdf-cta,
.frm_edit_link,
.frm_prev_page,
.frm_next_page,
.frm_submit,
.no-print{
  display: none !important;
}

/* Cache le message Formidable "No Entries Found" dans le PDF */
.frm_no_entries,
.frm_no_entries *,
.frm_empty,
.frm_message.frm_no_entries { 
  display: none !important; 
  visibility: hidden !important; 
  height: 0 !important; 
  margin: 0 !important; 
  padding: 0 !important;
}

  /* Cache d’éventuels éléments de ta View non désirés */
  .no-print{ display:none; }


/* Empêche une coupure au milieu d’une ligne de 4 */
.ft-grid tr{ page-break-inside: avoid; }

/* Légendes longues : retour à la ligne propre */
.ft-caption{ word-break: break-word; }

/* Légendes alignées même si le texte varie un peu */
.ft-caption{
  margin-top:2mm;
  font-size:9.5pt; color:#555;
  min-height:8mm;        /* réserve la même “ligne de base” */
}

/* S’il n’y a pas de légende : on ne prend pas d’espace */
.ft-caption:empty{ display:none; min-height:0; }

/* Logo du header : taille fixe comme avant */
.fiche-header img,
.fiche-header .fiche-logo{
  height:12mm !important;     /* ajuste si tu veux 10–14mm */
  width:auto !important;
  max-width:240px !important;  /* sécurité : tu peux enlever ou ajuster */
  float:right !important;      /* garde-le à droite comme prévu */
}


</style>
</head>
<body>
  <main class="pdf-body">
    <?php
      // On travaille sur le HTML de la View.
      $doc = new DOMDocument();
      libxml_use_internal_errors(true);
      $doc->loadHTML('<?xml encoding="utf-8" ?>'.$body);
      libxml_clear_errors();
      $xp = new DOMXPath($doc);

      // 0) Retire l’éventuel bouton "Télécharger le PDF" résiduel
      foreach ($xp->query("//a[contains(concat(' ',normalize-space(@class),' '),' no-print ')]") as $a) {
        $a->parentNode->removeChild($a);
      }
      
      // 0bis) Supprime les messages "No Entries Found" de Formidable
foreach ($xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' frm_no_entries ')]") as $n) {
  $n->parentNode->removeChild($n);
}

// NORMALISE CHAQUE figure.ft-photo : on veut toujours .ft-photo-box > .ft-photo-img
foreach ($xp->query("//figure[contains(concat(' ', normalize-space(@class), ' '), ' ft-photo ')]") as $fig) {
    // 1) Récupère l’éventuel <img> ou <div.ft-photo-img>
    $img = $xp->query(".//img", $fig)->item(0);
    $divImg = $xp->query(".//div[contains(concat(' ', normalize-space(@class), ' '), ' ft-photo-img ')]", $fig)->item(0);

    // 2) Construis la box + l’élément image final
    $box = $doc->createElement('div'); $box->setAttribute('class', 'ft-photo-box');
    $imgDiv = $doc->createElement('div'); $imgDiv->setAttribute('class', 'ft-photo-img');

    if ($img) {
        // Cas <img src="...">
        $src = $img->getAttribute('src');
        if ($src) {
            $imgDiv->setAttribute('style', 'background-image:url(' . htmlspecialchars($src, ENT_QUOTES) . ')');
        }
    } elseif ($divImg) {
        // Cas déjà transformé : on récupère le background-image existant
        $style = $divImg->getAttribute('style'); // contient background-image:url(...)
        if ($style) $imgDiv->setAttribute('style', $style);
    }

    // 3) Nettoie le contenu visuel existant dans la figure (on garde la figcaption si présente)
    foreach (iterator_to_array($fig->childNodes) as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower($child->nodeName);
            if ($tag === 'img' || ($tag === 'div' && strpos(' ' . $child->getAttribute('class') . ' ', ' ft-photo-img ') !== false)) {
                $fig->removeChild($child);
            }
        }
        if ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) === '') {
            $fig->removeChild($child);
        }
    }

    // 4) Injecte la structure normalisée
    $box->appendChild($imgDiv);
    $fig->insertBefore($box, $fig->firstChild);
}




// 0quater) Regrouper les figures par 4 dans un <table class="ft-grid"> + padding cells
foreach ($xp->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' photos ')]") as $photosSec) {
    $figs = iterator_to_array($xp->query(".//figure[contains(concat(' ', normalize-space(@class), ' '), ' ft-photo ')]", $photosSec));
    if (count($figs) === 0) continue;

    $table = $doc->createElement('table'); $table->setAttribute('class','ft-grid');
    $tbody = $doc->createElement('tbody'); $table->appendChild($tbody);

    $row = null; $i = 0;
    foreach ($figs as $fig) {
        if ($i % 4 === 0) { $row = $doc->createElement('tr'); $tbody->appendChild($row); }
        // Dans la boucle qui crée chaque <td> :
$td = $doc->createElement('td'); // pas de style inline ici
$td->appendChild($fig->cloneNode(true));
$row->appendChild($td);
        $i++;
        // retire la figure originale
        $fig->parentNode->removeChild($fig);
    }

    // PAD: si la dernière ligne n'a pas 4 cellules, on complète avec des cellules vides
    $reste = $i % 4;
    if ($reste !== 0) {
        for ($k = $reste; $k < 4; $k++) {
            $td = $doc->createElement('td');
            // petit placeholder neutre pour stabiliser la hauteur sans visuel
            $placeholder = $doc->createElement('div');
            $placeholder->setAttribute('style','height:0; line-height:0; font-size:0;');
            $td->appendChild($placeholder);
            $row->appendChild($td);
        }
    }

    $photosSec->appendChild($table);
}



      // 1) Écrit "ZONE 1/2/3..." côté serveur (Dompdf n'a pas les counters CSS)
      $i = 1;
      foreach ($xp->query("//*[contains(concat(' ',normalize-space(@class),' '),' zone ')]") as $zone) {
        $left = $xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' zone-left ')]", $zone)->item(0);
        if ($left && !trim($left->textContent)) {
          $left->nodeValue = "ZONE ".$i;
        }
        $i++;
      }

      // 2) convertit chaque .kv2 .col en tableau K/V robuste tout en gardant .col
foreach ($xp->query("//*[contains(concat(' ',normalize-space(@class),' '),' kv2 ')]") as $kv2) {
  foreach ($xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' col ')]", $kv2) as $col) {

    // Nouveau conteneur .col pour préserver la grille 2 colonnes
    $wrapper = $doc->createElement('div');
    $wrapper->setAttribute('class', 'col');

    // Tableau K/V
    $table = $doc->createElement('table');

    // On repère les paires .k / .v (on ignore le texte blanc)
    $ks = $xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' k ')]", $col);
    $vs = $xp->query(".//*[contains(concat(' ',normalize-space(@class),' '),' v ')]", $col);
    $count = min($ks->length, $vs->length);

    for ($r = 0; $r < $count; $r++) {
      $tr  = $doc->createElement('tr');
      $tdk = $doc->createElement('td'); $tdk->setAttribute('class','k');
      $tdv = $doc->createElement('td'); $tdv->setAttribute('class','v');

      $tdk->appendChild($ks->item($r)->cloneNode(true));
      $tdv->appendChild($vs->item($r)->cloneNode(true));

      $tr->appendChild($tdk);
      $tr->appendChild($tdv);
      $table->appendChild($tr);
    }

    $wrapper->appendChild($table);

    // On remplace l'ancienne .col par la nouvelle .col contenant le <table>
    $col->parentNode->replaceChild($wrapper, $col);
  }
}


      // 3) (option) enlève un éventuel 2e H1 si ta View en génère plusieurs
      $h1s = $xp->query("//h1");
      if ($h1s->length > 1) {
        for ($j=1; $j<$h1s->length; $j++) {
          $h1s->item($j)->parentNode->removeChild($h1s->item($j));
        }
      }

      // Sortie finale
      echo $doc->saveHTML($doc->getElementsByTagName('body')->item(0));
    ?>
  </main>
</body>
</html>
