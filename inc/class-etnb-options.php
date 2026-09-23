<?php
/**
 * 保存する値（オプション）の読み書きと、入力値の検査。
 *
 * 保存するオプションは2つ。
 * - etnb_notice … お知らせの中身（見出し・本文・掲載開始日・掲載終了日）。編集者も書き換える。
 * - etnb_layout … 出し方（PC・スマホそれぞれ「重ねる／帯」）。管理者だけが書き換える。
 *
 * @package etbs-notice-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * オプションの読み書きをまとめたクラス。
 */
class ETNB_Options {

	/**
	 * お知らせの中身を保存するオプション名。
	 */
	const NOTICE = 'etnb_notice';

	/**
	 * 出し方を保存するオプション名。
	 */
	const LAYOUT = 'etnb_layout';

	/**
	 * 出し方：スライダーなど直後の要素に重ねる。
	 */
	const MODE_OVERLAY = 'overlay';

	/**
	 * 出し方：高さを持った帯として置く（下の要素を押し下げる）。
	 */
	const MODE_BAND = 'band';

	/**
	 * お知らせの初期値を返す。
	 *
	 * @return array{heading:string,body:string,start:string,end:string} 初期値。
	 */
	public static function notice_defaults() {
		return array(
			'heading' => __( '休業日のお知らせ', 'etbs-notice-banner' ),
			'body'    => '',
			'start'   => '',
			'end'     => '',
		);
	}

	/**
	 * 出し方の初期値を返す。PC は重ねる・スマホは帯。
	 *
	 * @return array{pc:string,sp:string} 初期値。
	 */
	public static function layout_defaults() {
		return array(
			'pc' => self::MODE_OVERLAY,
			'sp' => self::MODE_BAND,
		);
	}

	/**
	 * 保存済みのお知らせを、欠けたキーを初期値で埋めて返す。
	 *
	 * @return array{heading:string,body:string,start:string,end:string} お知らせ。
	 */
	public static function get_notice() {
		$saved = get_option( self::NOTICE, array() );

		// 保存値が壊れていても（配列でなくても）初期値で動くようにする。
		return self::fill( is_array( $saved ) ? $saved : array(), self::notice_defaults() );
	}

	/**
	 * 保存済みの出し方を、欠けたキー・不正な値を初期値で埋めて返す。
	 *
	 * @return array{pc:string,sp:string} 出し方。
	 */
	public static function get_layout() {
		$saved = get_option( self::LAYOUT, array() );

		return self::sanitize_layout( is_array( $saved ) ? $saved : array() );
	}

	/**
	 * サイトのタイムゾーン（設定 → 一般）を返す。
	 *
	 * タイムゾーンの取り方をここ1か所にまとめる（ETNB_Schedule は WordPress に依存させないため、そちらには置かない）。
	 *
	 * @return DateTimeZone サイトのタイムゾーン。
	 */
	public static function site_timezone() {
		return ETNB_Schedule::site_timezone( get_option( 'timezone_string' ), get_option( 'gmt_offset' ) );
	}

	/**
	 * 本文・見出しが「空」かを判定する。全角スペース（U+3000）や改行だけのものも空とみなす。
	 *
	 * PHP の trim() は全角スペースを取り除かないため、施設様が全角スペースだけを入れると空の箱が出てしまう。
	 *
	 * @param string $text 判定する文字列。
	 * @return bool 空なら true。
	 */
	public static function is_blank( $text ) {
		return '' === preg_replace( '/\A[\s\x{3000}]+|[\s\x{3000}]+\z/u', '', (string) $text );
	}

	/**
	 * 入力されたお知らせを検査し、保存する値とエラーを返す。
	 *
	 * エラーがあるときは保存しない前提で、入力値（整えたもの）も返す（画面に入力を残すため）。
	 *
	 * @param array $input 画面から送られた値（wp_unslash 済み）。
	 * @return array{0:array{heading:string,body:string,start:string,end:string},1:string[]} [ 整えた値, エラー文の配列 ]。
	 */
	public static function sanitize_notice( array $input ) {
		$errors = array();

		// 見出しは1行。改行やタグを取り除く。
		$heading = isset( $input['heading'] ) ? sanitize_text_field( (string) $input['heading'] ) : '';

		// 本文は複数行。改行は残し、タグは取り除く。
		$body = isset( $input['body'] ) ? sanitize_textarea_field( (string) $input['body'] ) : '';

		// 日付は空か、実在する Y-m-d だけを受け付ける。
		$start = isset( $input['start'] ) ? trim( (string) $input['start'] ) : '';
		$end   = isset( $input['end'] ) ? trim( (string) $input['end'] ) : '';

		if ( '' !== $start && ! ETNB_Schedule::is_valid_date( $start ) ) {
			$errors[] = __( '掲載開始日の形式が正しくありません。', 'etbs-notice-banner' );
			$start    = '';
		}
		if ( '' !== $end && ! ETNB_Schedule::is_valid_date( $end ) ) {
			$errors[] = __( '掲載終了日の形式が正しくありません。', 'etbs-notice-banner' );
			$end      = '';
		}

		// 終了日が開始日より前だと、一度も表示されないまま終わる。入力ミスとして止める。
		// Y-m-d は文字列比較で日付の前後と一致する。
		if ( '' !== $start && '' !== $end && $end < $start ) {
			$errors[] = __( '掲載終了日が掲載開始日より前になっています。', 'etbs-notice-banner' );
		}

		return array(
			array(
				'heading' => $heading,
				'body'    => $body,
				'start'   => $start,
				'end'     => $end,
			),
			$errors,
		);
	}

	/**
	 * 出し方を検査し、許可された値だけにして返す。不正な値は初期値に戻す。
	 *
	 * @param array $input 画面から送られた値、または保存値。
	 * @return array{pc:string,sp:string} 整えた出し方。
	 */
	public static function sanitize_layout( array $input ) {
		$defaults = self::layout_defaults();
		$allowed  = array( self::MODE_OVERLAY, self::MODE_BAND );
		$layout   = array();

		// PC・スマホそれぞれ、許可された2値のどちらかでなければ初期値を使う。
		foreach ( $defaults as $key => $default ) {
			$value          = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$layout[ $key ] = in_array( $value, $allowed, true ) ? $value : $default;
		}

		return $layout;
	}

	/**
	 * 保存値の欠けたキーを初期値で埋め、初期値に無いキーは捨てる。値は文字列にそろえる。
	 *
	 * @param array $saved    保存値。
	 * @param array $defaults 初期値。
	 * @return array 埋めた値。
	 */
	private static function fill( array $saved, array $defaults ) {
		$filled = array();

		foreach ( $defaults as $key => $default ) {
			$filled[ $key ] = ( isset( $saved[ $key ] ) && is_scalar( $saved[ $key ] ) ) ? (string) $saved[ $key ] : $default;
		}

		return $filled;
	}
}
