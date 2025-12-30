add_filter('show_admin_bar', '__return_false');

add_action('wp_head', function () {
  echo '<style id="cee-no-wpbar-bump">html{margin-top:0!important}</style>';
}, 0);

/* Ajoute la classe is-entry au <body> côté serveur (pas besoin de JS) */
add_filter('body_class', function($classes){
  if (cee_is_entry_page()) $classes[] = 'is-entry';
  return $classes;
});

add_filter('frm_upload_filename', function ($filename, $args) {
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $eid  = isset($args['entry_id']) ? $args['entry_id'] : uniqid();
    $fid  = isset($args['field_id']) ? $args['field_id'] : 'x';
    $base = 'entry-' . $eid . '-field-' . $fid . '-' . time() . '-' . wp_generate_password(6, false, false);
    return $base . '.' . strtolower($ext);
}, 10, 2);

// Ranger les fichiers dans un sous-dossier par entrée (100% compatible)
add_filter('frm_upload_folder', function ($path /*, ... */) {
    // Récupère TOUT ce que Formidable a passé au hook
    $args_list = func_get_args(); // [ $path, $form, $field, $atts?, $args? ]

    // Tente de trouver un entry_id dans n'importe quel argument tableau
    $entry_id = 0;
    foreach ($args_list as $arg) {
        if (is_array($arg) && !empty($arg['entry_id'])) {
            $entry_id = (int) $arg['entry_id'];
            break;
        }
    }

    if ($entry_id > 0) {
        $new = trailingslashit($path) . $entry_id . '/';
        // Crée le dossier si nécessaire sans casser si ça échoue
        if (!file_exists($new)) {
            @wp_mkdir_p($new);
        }
        if (file_exists($new)) {
            return $new;
        }
    }
    return $path;
}, 10, 5); // on accepte jusqu'à 5 args pour couvrir tous les cas

add_filter('frm_upload_filename', function ($filename, $args) {
    $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === '') $ext = 'jpg'; // garde une extension par défaut
    $eid  = isset($args['entry_id']) ? $args['entry_id'] : uniqid();
    $fid  = isset($args['field_id']) ? $args['field_id'] : 'x';
    $base = 'entry-' . $eid . '-field-' . $fid . '-' . time() . '-' . wp_generate_password(6, false, false);
    return $base . '.' . $ext;
}, 10, 2);


// 3) Demander à Formidable de créer une pièce jointe WP (Médiathèque) pour chaque fichier
add_filter('frm_upload_to_media_library', function($send_to_library, $args){
    return true; // envoie l'upload dans la médiathèque
}, 10, 2);

/* ===========================
   CEE – Icône Power login/logout
   =========================== */
add_shortcode('cee_power_icon', function () {
  $is_logged = is_user_logged_in();
  $url   = $is_logged ? wp_logout_url( CEE_LOGIN_URL ) : CEE_LOGIN_URL;
  $label = $is_logged ? 'Se déconnecter' : 'Se connecter';

  // L’icône que tu as fourni (stroke = currentColor pour hériter de la couleur du header)
  $svg = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="cee-power__icon" aria-hidden="true" focusable="false">
    <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
  </svg>';

  return '<a class="cee-power" href="'. esc_url($url) .'" title="'. esc_attr($label) .'" aria-label="'. esc_attr($label) .'">'.$svg.'<span class="screen-reader-text">'. esc_html($label) .'</span></a>';
});

/* CEE – Sommes-nous sur une page "entry" ? */
function cee_is_entry_page(){
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  return (strpos($uri, '/entry/') !== false);
}


/* ===========================
   CEE – Header, Footer
   =========================== */

function cee_header_shortcode(){
  $fallback_logo = 'https://fichetechcee.fr/wp-content/uploads/2025/10/logo-eco-vert-1.png';
  ob_start(); ?>
  <header class="cee-header" role="banner">
    <div class="cee-header__inner">
      <a class="cee-logo" href="<?php echo esc_url( home_url('/') ); ?>">
        <?php if ( function_exists('the_custom_logo') && has_custom_logo() ) {
          the_custom_logo();
        } else {
          echo '<img src="'. esc_url($fallback_logo) .'" alt="'. esc_attr( get_bloginfo('name') ) .'" loading="eager" decoding="async" />';
        } ?>
      </a>

      <div class="cee-actions">
        <?php
          $is_auth_page = (defined('CEE_LOGIN_PAGE_ID') && CEE_LOGIN_PAGE_ID && is_page(CEE_LOGIN_PAGE_ID)) || is_page('inscription');
          if (!$is_auth_page) echo do_shortcode('[cee_power_icon]');
        ?>
      </div>
    </div>
  </header>
  <?php return ob_get_clean();
}

add_shortcode('cee_header','cee_header_shortcode');

function cee_footer_shortcode(){
  ob_start(); ?>

  <?php if ( cee_is_entry_page() ) : ?>
  <nav class="cee-backbar" aria-label="Navigation secondaire">
    <?php
      $target = current_user_can('manage_options')
        ? '/'.CEE_SLUG_ALL.'/'   // admin → toutes les fiches
        : '/'.CEE_SLUG_MINE.'/'; // technicien → mes fiches
    ?>
    <a class="cee-back" href="<?php echo esc_url( home_url($target) ); ?>"
       title="Retour à la liste">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2.4" class="cee-back__icon" aria-hidden="true" focusable="false">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="m11.25 9-3 3m0 0 3 3m-3-3h7.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
      </svg>
      <span class="screen-reader-text">Retour</span>
    </a>
  </nav>
<?php endif; ?>


  <footer class="cee-footer" role="contentinfo">
    <div class="cee-footer__inner">
      <p>© <?php echo date('Y'); ?> Éco Environnement</p>
    </div>
  </footer>
  <?php return ob_get_clean();
}

add_shortcode('cee_footer','cee_footer_shortcode');

add_action('wp_body_open', function(){ echo do_shortcode('[cee_header]'); }, 5);
add_action('wp_footer',     function(){ echo do_shortcode('[cee_footer]'); }, 100);


/* =========================================
   CEE – Résolution page Login + constantes
   ========================================= */
function cee_get_login_page(){
  foreach (array('connection','connexion','login','se-connecter','login-2') as $path){
    $p = get_page_by_path($path);
    if ($p && $p->post_status === 'publish') return $p;
  }
  return null;
}

add_action('init', function(){
  $login_page = cee_get_login_page();

  if (!defined('CEE_LOGIN_PAGE_ID')) define('CEE_LOGIN_PAGE_ID', $login_page ? (int)$login_page->ID : 0);
  if (!defined('CEE_LOGIN_URL'))     define('CEE_LOGIN_URL',     $login_page ? get_permalink($login_page) : wp_login_url());

  if (!defined('CEE_SLUG_HOME')) define('CEE_SLUG_HOME',  'accueil');
if (!defined('CEE_SLUG_ALL'))  define('CEE_SLUG_ALL',   'mes-fiches-2'); // page ADMIN (toutes les fiches)
if (!defined('CEE_SLUG_MINE')) define('CEE_SLUG_MINE',  'mes-fiches');   // page TECH (mes fiches)


  // Rôle Technicien si absent
  if (!get_role('technician')) add_role('technician','Technicien', array('read'=>true));
});


/* ===========================
   CEE – Accès & redirections (front)
   =========================== */
function cee_is_builder_or_admin_context(){
  if (is_admin() || wp_doing_ajax()) return true;
  if (defined('REST_REQUEST') && REST_REQUEST) return true;
  if (function_exists('is_customize_preview') && is_customize_preview()) return true;
  if (!empty($_GET['elementor-preview']) || !empty($_GET['preview'])) return true;
  return false;
}

add_action('template_redirect', function () {
  if (cee_is_builder_or_admin_context()) return;

  $is_auth_page   = (CEE_LOGIN_PAGE_ID && is_page(CEE_LOGIN_PAGE_ID)) || is_page('inscription');
  $is_public_slug = is_page(array('privacy-policy','inscription'));

  // Non connecté : tout sauf pages publiques → page login
  if (!is_user_logged_in()){
    if (!$is_auth_page && !$is_public_slug){
      wp_safe_redirect(CEE_LOGIN_URL);
      exit;
    }
    // No-cache sur les pages d'auth pour éviter HTML en cache
    if ($is_auth_page){ nocache_headers(); }
    return;
  }

  // Connecté : si on visite login/inscription, envoyer à l’accueil
  if ($is_auth_page){
    wp_safe_redirect( home_url('/'.CEE_SLUG_HOME.'/') );
    exit;
  }

  // Non-admin sur /fiches-existantes -> /mes-fiches
  if (!current_user_can('manage_options') && is_page(CEE_SLUG_ALL)){
    wp_safe_redirect( home_url('/'.CEE_SLUG_MINE.'/') );
    exit;
  }
}, 9);


/* ===========================
   CEE – Redirections login/logout
   =========================== */

/* Après login → respecte redirect_to si présent & sûr, sinon /accueil */
add_filter('login_redirect', function($redirect_to, $request, $user){
  if (is_wp_error($user) || !is_object($user)) return $redirect_to;

  // Si on a un redirect_to valide, on le garde, surtout pour wp-admin
  if (!empty($request)){
    // sécurise l’URL
    $safe = wp_validate_redirect($request, home_url('/'.CEE_SLUG_HOME.'/'));
    return $safe;
  }
  return home_url('/'.CEE_SLUG_HOME.'/');
}, 10, 3);

/* Après déconnexion → page login (ou home si pas trouvée) */
add_filter('logout_redirect', function($redirect_to, $requested, $user){
  return CEE_LOGIN_URL ?: home_url('/');
}, 10, 3);

/* =======================
   Mise en page Formidable
   ======================= */
/**
 * 💣 Empêche Formidable de générer des <p> autour des shortcodes.
 */
add_filter('frm_use_wpautop', '__return_false');


/* ===========================
   CEE – Icône Déconnexion + liens
   =========================== */

add_shortcode('cee_logout', function(){
  if (!is_user_logged_in()) return '';
  return '<a class="cee-btn cee-btn--logout" href="'. esc_url( wp_logout_url( CEE_LOGIN_URL ) ) .'">Se déconnecter</a>';
});

add_shortcode('cee_logout_icon', function(){
  if (!is_user_logged_in()) return '';
  $icon_url = 'https://fichetechcee.fr/wp-content/uploads/2025/11/off.png';
  return '<a class="cee-logout-icon" href="'. esc_url( wp_logout_url( CEE_LOGIN_URL ) ) .'" title="Se déconnecter" aria-label="Se déconnecter"><img src="'. esc_url($icon_url) .'" alt="Se déconnecter" width="40" height="40" loading="lazy" decoding="async" /></a>';
});

/* Lien Inscription + MDP oublié sous le formulaire de login */
add_shortcode('cee_auth_links', function(){
  if (is_user_logged_in()) return '';
  $register_url = home_url('/inscription/');
  $lostpass_url = wp_lostpassword_url( CEE_LOGIN_URL );
  return '<p class="cee-auth-links">Pas encore inscrit(e) ? <a href="'.esc_url($register_url).'">Créer un compte</a> <span class="sep">·</span> <a href="'.esc_url($lostpass_url).'">Mot de passe oublié&nbsp;?</a></p>';
});

// Autoriser HEIC/HEIF à l'upload (sinon WP les bloque)
add_filter('mime_types', function($mimes){
  $mimes['heic'] = 'image/heic';
  $mimes['heif'] = 'image/heif';
  return $mimes;
});

// Convertir HEIC/HEIF -> JPG à l'upload
add_filter('wp_handle_upload', function($upload){
  $file = $upload['file'];
  $type = $upload['type'];

  // Si ce n'est pas du HEIC/HEIF, on ne touche pas
  if (!in_array($type, ['image/heic','image/heif'], true)) {
    return $upload;
  }

  if (!class_exists('Imagick')) {
    // Pas d'Imagick, on n’essaie pas (sinon on casserait le fichier)
    return $upload;
  }

  try {
    $img = new Imagick($file);
    $img->setImageFormat('jpeg');
    $img->setImageCompression(Imagick::COMPRESSION_JPEG);
    $img->setImageCompressionQuality(85);
    $img->stripImage();

    $new_path = preg_replace('/\.(heic|heif)$/i', '.jpg', $file);
    if ($img->writeImage($new_path)) {
      // Remplace le fichier & les métadonnées
      unlink($file);
      $upload['file'] = $new_path;
      $upload['type'] = 'image/jpeg';
      $upload['url']  = preg_replace('/\.(heic|heif)$/i', '.jpg', $upload['url']);
    }
    $img->clear();
    $img->destroy();
  } catch (Exception $e) {
    // en cas d’erreur, on laisse tel quel pour ne pas bloquer l’upload
  }
  return $upload;
}, 20);

add_filter('wp_calculate_image_srcset', function($sources){
  if ( is_singular() && strpos($_SERVER['REQUEST_URI'], 'fiche') !== false ) {
    return false; // pas de srcset sur la page fiche
  }
  return $sources;
}, 10, 1);

/* ===========================
   CEE – Menu d’actions (PDF + partage)
   =========================== */
add_shortcode('cee_actions', function($atts = []){
  // 1) Récupère l'ID d'entrée passé par la vue : [cee_actions entry=[id]]
  $a = shortcode_atts(['entry' => 0], $atts, 'cee_actions');
  $entry_id = intval($a['entry']);
  if ($entry_id <= 0) return ''; // sécurité

  // 2) URLs utiles — TOUJOURS avec l'ID d'entrée
  $pdf_url = add_query_arg('eco-pdf', $entry_id, home_url('/'));

  $title = get_the_title() ?: 'Fiche technique';
  $txt   = rawurlencode($title.' – PDF : '.$pdf_url);

  // Partage (fallbacks)
  $wa   = 'https://wa.me/?text='.$txt;
  $mail = 'mailto:?subject='.rawurlencode($title).'&body='.$txt;
  $is_android = stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Android') !== false;
  $sms  = $is_android ? 'sms:?body='.$txt : 'sms:&body='.$txt;

  // 3) URL "Modifier" (page front : slug `modifier-une-fiche`)
  $edit_url = '';
  if ($p = get_page_by_path('modifier-une-fiche')) {
    $edit_url = add_query_arg(
      ['frm_action' => 'edit', 'entry' => $entry_id],
      get_permalink($p)
    );
  }

  // 4) Supprimer (réservé admin)
  $delete_url = current_user_can('manage_options') ? get_delete_post_link(get_the_ID(), '', true) : '';

  ob_start(); ?>
  <div class="cee-actions-menu" data-pdf="<?php echo esc_url($pdf_url); ?>" data-title="<?php echo esc_attr($title); ?>">
    <button class="cee-actions-menu__btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Actions">
      <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      <span>ACTIONS</span>
    </button>

    <div class="cee-actions-menu__list" hidden>
  <a class="cee-actions-menu__item" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener">Télécharger le PDF</a>

  <!-- Partage système (Mail, Messages, AirDrop…) -->
  <button class="cee-actions-menu__item js-share" type="button">Partager…</button>

  <!-- Raccourci direct WhatsApp (optionnel) -->
  <a class="cee-actions-menu__item" href="<?php echo esc_url($wa); ?>" target="_blank" rel="noopener">WhatsApp</a>

  <?php if ( current_user_can('manage_options') ) : ?>
    <hr class="cee-actions-menu__sep">
    <?php if ($edit_url): ?>
      <a class="cee-actions-menu__item" href="<?php echo esc_url($edit_url); ?>">Modifier</a>
    <?php endif; ?>
    <?php if ($delete_url): ?>
      <a class="cee-actions-menu__item cee-danger"
         href="<?php echo esc_url($delete_url); ?>"
         onclick="return confirm('Supprimer définitivement cette fiche ?');">Supprimer</a>
    <?php endif; ?>
  <?php endif; ?>
</div>

  </div>
  <?php return ob_get_clean();
});

add_action('wp_footer', function () {
  ?>
  <script>
  (function(){
    const wrap = document.querySelector('.cee-actions-menu');
    if(!wrap) return;

    const btn   = wrap.querySelector('.cee-actions-menu__btn');
    const panel = wrap.querySelector('.cee-actions-menu__list');
    const share = wrap.querySelector('.js-share');
    let backdrop = null;

    const H_MARGIN = 12;
    const MAX_VH   = 0.60;

    const isHidden = el => el.hasAttribute('hidden');
    const show     = el => el.removeAttribute('hidden');
    const hide     = el => el.setAttribute('hidden','');

    function clamp(v, min, max){ return Math.max(min, Math.min(v, max)); }

    function placePanel(){
      // largeur cible
      const maxW = Math.min(520, Math.round(window.innerWidth * 0.92));

      // rendre mesurable
      panel.style.visibility = 'hidden';
      panel.style.opacity = '0';
      show(panel);

      // 👉 d'abord on lit le bouton
      const b = btn.getBoundingClientRect();

      // hauteur max
      const maxH   = Math.round(window.innerHeight * MAX_VH);
      const panelH = Math.min(panel.scrollHeight, maxH);

      // 👉 toujours OUVERT VERS LE HAUT
      let top  = Math.round(b.top - 12 - panelH);
      top      = clamp(top, H_MARGIN, window.innerHeight - panelH - H_MARGIN);

      // centré horizontalement sur le bouton
      let left = Math.round(b.left + b.width/2 - maxW/2);
      left     = clamp(left, H_MARGIN, window.innerWidth - maxW - H_MARGIN);

      // position fixe + anti-conflits
      panel.style.setProperty('position','fixed','important');
      panel.style.setProperty('left',  left + 'px', 'important');
      panel.style.setProperty('top',   top  + 'px', 'important');
      panel.style.setProperty('right','auto','important');
      panel.style.setProperty('bottom','auto','important');
      panel.style.setProperty('margin','0','important');
      panel.style.setProperty('transform','none','important');
      panel.style.setProperty('width', maxW + 'px', 'important');
      panel.style.setProperty('max-height', maxH + 'px', 'important');
      panel.style.setProperty('overflow','auto','important');
      panel.style.setProperty('-webkit-overflow-scrolling','touch','important');
      panel.style.setProperty('z-index','100000','important');

      panel.style.visibility = '';
      panel.style.opacity    = '';
    }

    function lockPageScroll(){ document.body.style.overflow = 'hidden'; document.body.style.touchAction='none'; }
    function unlockPageScroll(){ document.body.style.overflow = ''; document.body.style.touchAction=''; }

    function openMenu(){
      backdrop = document.createElement('div');
      backdrop.className = 'cee-actions-backdrop';
      document.body.appendChild(backdrop);
      backdrop.addEventListener('click', closeMenu, { once:true });

      lockPageScroll();
      placePanel();

      window.addEventListener('resize', placePanel);
      window.addEventListener('orientationchange', placePanel);
    }

    function closeMenu(){
      if(backdrop){ backdrop.remove(); backdrop = null; }
      hide(panel);
      unlockPageScroll();
      window.removeEventListener('resize', placePanel);
      window.removeEventListener('orientationchange', placePanel);
    }

    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if(isHidden(panel)) openMenu(); else closeMenu();
    });

    document.addEventListener('keydown', (e) => {
      if(e.key === 'Escape' && !isHidden(panel)) closeMenu();
    });

    if (share) {
      share.addEventListener('click', async () => {
        const pdf   = wrap.getAttribute('data-pdf')   || location.href;
        const title = wrap.getAttribute('data-title') || document.title;
        try {
          if (navigator.share) {
            await navigator.share({ title, text: title, url: pdf });
          } else {
            await navigator.clipboard?.writeText(pdf);
            location.href = 'mailto:?subject=' + encodeURIComponent(title) +
                            '&body=' + encodeURIComponent(pdf);
          }
        } catch(_) {}
      });
    }
  })();
  </script>
  <?php
}, 100);
