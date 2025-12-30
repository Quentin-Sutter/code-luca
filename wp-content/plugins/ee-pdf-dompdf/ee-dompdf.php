<?php
/**
 * Plugin Name: EE PDF (Dompdf)
 * Description: Génère un PDF d'une fiche via Dompdf. URL: /?ee-pdf=ENTRY_ID
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

class EE_Dompdf_Plugin {
  const VIEW_ID = 1429;

  public function __construct() {
    add_action('init', [$this, 'maybe_render_pdf']);
  }

  private function dompdf_bootstrap() {
    $autoload = WP_CONTENT_DIR . '/uploads/dompdf/autoload.inc.php';
    if (!file_exists($autoload)) {
      wp_die("Dompdf introuvable. Place le dossier 'Dompdf' dans wp-content/uploads/dompdf");
    }
    require_once $autoload;

    // Options Dompdf
    $opts = new Dompdf\Options();
    $opts->set('isHtml5ParserEnabled', true);
    $opts->set('isRemoteEnabled', true); // autorise logo/images distants
    $opts->set('defaultFont', 'Helvetica'); // unicode friendly
    return $opts;
  }

  public function maybe_render_pdf() {
    if (empty($_GET['ee-pdf'])) return;

    $entry_id = intval($_GET['ee-pdf']);
    if (!$entry_id) wp_die('Paramètre ee-pdf manquant.');

    // Récupère le HTML de ta View pour CETTE entrée (via shortcode)
    $view_id = intval(self::VIEW_ID);
    if (!$view_id) wp_die('Configure VIEW_ID dans le plugin.');

    // Important : on force un contexte frontal sans l’adminbar
    show_admin_bar(false);

    // Ton shortcode Formidable (adapte si besoin)
    $shortcode = sprintf(
      '[display-frm-data id="%d" entry="%d"]',
      $view_id,
      $entry_id
    );

    // Charge le gabarit HTML
    ob_start();
    $html = do_shortcode($shortcode);
    // Nettoyage : pas de scripts, pas de lien "Télécharger le PDF", pas de tags Formidable résiduels
$html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
$html = preg_replace('#<a[^>]*class=["\']no-print["\'][^>]*>.*?</a>#is', '', $html);
// (optionnel) si des [if] ou [foreach] trainent : on les supprime
$html = preg_replace('/\[(?:if|foreach)[^\]]*\]/i', '', $html);

    // Enrobe avec notre template (titre centré, CSS PDF, footer)
    $data = [
      'title' => 'FICHE TECHNIQUE',
      // mets ton URL logo absolut (HTTPS)
      'logo'  => 'https://fichetechcee.fr/wp-content/uploads/2025/10/logo-eco-vert.png',
      'body'  => $html,
      'footer'=> '© 2025 Éco Environnement',
    ];
    $this->render_template($data);
    $template = ob_get_clean();

    // Dompdf
    $opts = $this->dompdf_bootstrap();
    $dompdf = new Dompdf\Dompdf($opts);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($template, 'UTF-8');
    $dompdf->render();

    $filename = 'Fiche-'.$entry_id.'.pdf';
    $dompdf->stream($filename, ['Attachment' => true]); // téléchargement direct
    exit;
  }

  private function render_template(array $data){
    // rend templates/pdf-template.php
    $tpl = __DIR__ . '/templates/pdf-template.php';
    if (!file_exists($tpl)) wp_die('Template PDF manquant.');
    extract($data);
    include $tpl;
  }
}

new EE_Dompdf_Plugin();
