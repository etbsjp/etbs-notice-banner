<?php
/**
 * Plugin Name: ETBS Notice Banner
 * Version: 1.0.1
 * Description: トップページのヘッダー直下（スライダーの上）に、休業日などのお知らせを表示します。見出し・本文・掲載期間は管理画面の「お知らせバナー」から編集者も書き換えられます。
 * Author: ETBS (DAI)
 * Author URI: https://etbs.jp
 * Plugin URI: https://etbs.jp/product-category/wordpress-tools/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: etbs-notice-banner
 *
 * @package etbs-notice-banner
 */

// 直接アクセスされた場合は終了する。下の define も実行されるコードなので、それより前に置く。
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * 本体ファイルの絶対パス。plugin_row_meta で自分の行を見分ける・アセットの URL を作る、の2か所で使う。
 */
define( 'ETNB_PLUGIN_FILE', __FILE__ );

// 実装は inc/ に置く。このファイルは読み込むだけ。
require_once __DIR__ . '/inc/func.php';

/*
 * プラグインのアップデートチェック。
 * GitHub の dist ブランチを見て、版数が上がっていれば管理画面に更新を出す。
 */
require __DIR__ . '/inc/plugin-update-checker/plugin-update-checker.php';
$etnb_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/etbsjp/etbs-notice-banner/',
	__FILE__,
	'etbs-notice-banner'
);
$etnb_update_checker->setBranch( 'dist' );
