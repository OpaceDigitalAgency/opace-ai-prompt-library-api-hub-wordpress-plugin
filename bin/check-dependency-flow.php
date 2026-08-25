<?php
/**
 * Static release guard for the two-step AI-Scribe dependency flow.
 */

$root = dirname(__DIR__);
$php = file_get_contents($root . '/admin/class-ai-core-addons.php');
$js = file_get_contents($root . '/assets/js/admin.js');

if ($php === false || $js === false) {
    fwrite(STDERR, "Could not read dependency flow sources.\n");
    exit(1);
}

$checks = array(
    'install button is explicit' => strpos($php, "esc_html_e('Install AI-Scribe'") !== false,
    'activation button is separate' => strpos($php, "esc_html_e('Activate AI-Scribe and continue'") !== false,
    'install response exposes activation step' => strpos($php, "'next_action' => 'activate'") !== false,
    'browser changes the same card to activation' => strpos($js, "response.data.next_action") !== false,
);

$install_start = strpos($php, 'public function ajax_install_addon()');
$install_body = $install_start === false ? '' : substr($php, $install_start);
$checks['install handler does not activate AI-Scribe'] = strpos($install_body, 'activate_plugin(') === false;

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}
