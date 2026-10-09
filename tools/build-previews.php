<?php
/**
 * Génère les aperçus (5 thèmes × 12 modèles) avec les paramètres par défaut du module
 * et des données fictives, sans WHMCS.
 * Usage : php tools/build-previews.php  ->  previews/<thème>/*.html
 */

namespace WHMCS\Database {
    // Base de données factice : aucun paramètre enregistré, donc valeurs par défaut.
    class FakeQuery
    {
        public function where() { return $this; }
        public function pluck() { return $this; }
        public function all() { return []; }
    }
    class Capsule
    {
        public static function table() { return new FakeQuery(); }
    }
}

namespace {
    define('WHMCS', true);
    $mod = dirname(__DIR__) . '/whmcs/modules/addons/lewebmax_emails';
    require $mod . '/lib/settings.php';
    require $mod . '/lib/render.php';

    $root = dirname(__DIR__);
    $out = $root . '/previews';
    foreach (glob($out . '/*/*.html') ?: [] as $old) {
        unlink($old);
    }
    $sections = [];
    foreach (lwm_themes() as $slug => $theme) {
        @mkdir($out . '/' . $slug, 0777, true);
        $links = [];
        foreach (lwm_templates() as $name => $tpl) {
            list($subject, $html) = lwm_render_preview($slug, $name);
            file_put_contents("$out/$slug/{$tpl['fichier']}", $html);
            $links[] = '<li><a href="' . $slug . '/' . $tpl['fichier'] . '">' . htmlspecialchars($subject) . '</a> <small>(' . $name . ')</small></li>';
        }
        $sections[] = '<h2>' . $theme['nom'] . '</h2><p>' . htmlspecialchars($theme['description']) . '</p><ul style="line-height:1.9">' . implode("\n", $links) . '</ul>';
    }
    foreach (glob($root . '/standalone/*.html') as $f) {
        copy($f, $out . '/standalone-' . basename($f));
        $sections[] = '<h2>E-mail autonome</h2><ul><li><a href="standalone-' . basename($f) . '">' . basename($f) . '</a></li></ul>';
    }
    file_put_contents($out . '/index.html', "<!DOCTYPE html><html lang=\"fr\"><head><meta charset=\"utf-8\"><title>Aperçus e-mails LEWEBMAX</title></head>\n"
        . '<body style="font-family:Arial,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#0d0d2b;"><h1>Aperçus e-mails LEWEBMAX</h1>'
        . implode("\n", $sections) . "</body></html>\n");
    echo count(lwm_themes()) . ' thèmes × ' . count(lwm_templates()) . " modèles générés dans previews/\n";
}
