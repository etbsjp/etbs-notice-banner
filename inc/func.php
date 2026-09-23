<?php
/**
 * 読み込みと起動。
 *
 * @package etbs-notice-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 掲載期間の判定（WordPress に依存しない）。
require_once __DIR__ . '/class-etnb-schedule.php';
// 保存値の読み書きと入力値の検査。
require_once __DIR__ . '/class-etnb-options.php';
// 管理画面「お知らせバナー」。
require_once __DIR__ . '/class-etnb-admin.php';
// トップページへの表示。
require_once __DIR__ . '/class-etnb-front.php';

// 管理画面の処理は管理画面でだけ登録する。
if ( is_admin() ) {
	ETNB_Admin::init();
}

// 表示の差し込み口は、テーマが読み込まれた後に登録する（テーマ側のフィルターで差し込み口を変えられるように）。
add_action( 'after_setup_theme', array( 'ETNB_Front', 'init' ) );
