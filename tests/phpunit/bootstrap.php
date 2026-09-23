<?php
/**
 * PHPUnit のブートストラップ。
 *
 * このリポジトリには WordPress のテストスイート一式が無い（package.json を持たない純 PHP のプラグインで、
 * wp-env を使っていない）。代わりに、テスト対象が呼ぶ WordPress の関数だけを最小限のスタブとして
 * ここで定義し、依存の薄いロジックだけをユニットテストする。実際の WordPress の中での動き
 * （管理画面・トップページへの表示）は検証サイトで手で確かめ、PR に記録する。
 *
 * 実行: vendor/bin/phpunit
 *
 * @package etbs-notice-banner
 */

// プラグインのファイルは `if ( ! defined( 'ABSPATH' ) ) { exit; }` で始まるため、通過させるダミー値を定義する。
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

/**
 * get_option() スタブのオプションストアを空にする。各テストの setUp() から呼ぶ。
 *
 * @return void
 */
function etnb_test_reset() {
	$GLOBALS['etnb_test_options'] = array();
	$GLOBALS['etnb_test_filters'] = array();
}

/**
 * get_option() スタブにオプションを設定する。
 *
 * @param string $name  オプション名。
 * @param mixed  $value 値。
 * @return void
 */
function etnb_test_set_option( $name, $value ) {
	$GLOBALS['etnb_test_options'][ $name ] = $value;
}

/**
 * apply_filters() スタブに、フィルターの戻り値を固定で設定する。
 *
 * @param string $hook  フィルター名。
 * @param mixed  $value 返す値。
 * @return void
 */
function etnb_test_set_filter( $hook, $value ) {
	$GLOBALS['etnb_test_filters'][ $hook ] = $value;
}

/**
 * $GLOBALS['etnb_test_options'] を使う get_option() の最小スタブ。
 *
 * @param string $name          オプション名。
 * @param mixed  $default_value 未設定のときに返す値。
 * @return mixed
 */
function get_option( $name, $default_value = false ) {
	return isset( $GLOBALS['etnb_test_options'] ) && array_key_exists( $name, $GLOBALS['etnb_test_options'] )
		? $GLOBALS['etnb_test_options'][ $name ]
		: $default_value;
}

/**
 * etnb_test_set_filter() で設定した値を返す apply_filters() の最小スタブ。未設定なら渡された値をそのまま返す。
 *
 * @param string $hook  フィルター名。
 * @param mixed  $value 値。
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	return isset( $GLOBALS['etnb_test_filters'] ) && array_key_exists( $hook, $GLOBALS['etnb_test_filters'] )
		? $GLOBALS['etnb_test_filters'][ $hook ]
		: $value;
}

/**
 * 翻訳しない __() のスタブ。
 *
 * @param string $text   文字列。
 * @param string $domain テキストドメイン。
 * @return string
 */
function __( $text, $domain = 'default' ) { // phpcs:ignore
	unset( $domain );
	return $text;
}

/**
 * esc_html() のスタブ（WordPress と同じく、特殊文字を実体参照にする）。
 *
 * @param string $text 文字列。
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * esc_attr() のスタブ。
 *
 * @param string $text 文字列。
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

/**
 * esc_attr__() のスタブ。
 *
 * @param string $text   文字列。
 * @param string $domain テキストドメイン。
 * @return string
 */
function esc_attr__( $text, $domain = 'default' ) {
	unset( $domain );
	return esc_attr( $text );
}

/**
 * sanitize_text_field() の簡易スタブ（タグを除き、改行・タブを空白にして前後の空白を落とす）。
 *
 * @param string $str 文字列。
 * @return string
 */
function sanitize_text_field( $str ) {
	$str = strip_tags( (string) $str ); // phpcs:ignore
	$str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
	return trim( $str );
}

/**
 * sanitize_textarea_field() の簡易スタブ（タグを除き、改行は残して前後の空白を落とす）。
 *
 * @param string $str 文字列。
 * @return string
 */
function sanitize_textarea_field( $str ) {
	return trim( strip_tags( (string) $str ) ); // phpcs:ignore
}

require_once dirname( __DIR__, 2 ) . '/inc/class-etnb-schedule.php';
require_once dirname( __DIR__, 2 ) . '/inc/class-etnb-options.php';
require_once dirname( __DIR__, 2 ) . '/inc/class-etnb-front.php';
