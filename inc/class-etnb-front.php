<?php
/**
 * トップページへの表示。
 *
 * テーマの差し込み口（既定は Lightning の lightning_header_after＝ヘッダーの直後）にお知らせを出す。
 * 「重ねる」は高さ0の置き場を出し、中の箱を下へはみ出させて直後の要素（スライダーなど）に重ねる。
 * こうするとテーマのテンプレートやスライダー側の HTML に手を入れずに済む。
 * 「帯」は高さを持った普通の要素として置き、直後の要素を押し下げる。
 *
 * ページ全体がキャッシュされるサイトでは、掲載終了後も古い HTML が配られうる。
 * そのため終了時刻を HTML に埋め込み、ブラウザ側でも終了を過ぎていたら取り除く。
 *
 * ★ 差し込み口で最初に出す要素は、必ずお知らせの div にすること（前に <style> などを足さない）。
 *   Lightning の固定ヘッダーは「ヘッダーのすぐ次の要素」に上余白を付けるため、別の要素が先に来ると
 *   スライダーが固定ヘッダーの裏に潜る（print_expiry_script() の説明を参照）。
 *
 * @package etbs-notice-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * トップページへの表示をまとめたクラス。
 */
class ETNB_Front {

	/**
	 * 差し込み口の既定値。Lightning（G2 系）の header.php がヘッダーの直後で呼ぶアクション。
	 */
	const DEFAULT_HOOK = 'lightning_header_after';

	/**
	 * 重ねるときの、直後の要素の上端からの余白の既定値。
	 */
	const DEFAULT_OVERLAY_TOP = '8px';

	/**
	 * スタイルの登録名。
	 */
	const STYLE_HANDLE = 'etbs-notice-banner';

	/**
	 * フックを登録する。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		/**
		 * お知らせを出す差し込み口（アクション名）。
		 * テーマが変わって差し込み口の名前・位置が変わったら、このフィルターで差し替える。
		 *
		 * @param string $hook アクション名。
		 */
		$hook = (string) apply_filters( 'etnb_output_hook', self::DEFAULT_HOOK );
		add_action( $hook, array( __CLASS__, 'render' ) );
	}

	/**
	 * いま表示すべきか（トップページで、掲載期間内か）を返す。
	 *
	 * @return bool 表示するなら true。
	 */
	public static function should_display() {
		$display = false;

		// トップページだけに出す。
		if ( is_front_page() ) {
			$timezone = ETNB_Options::site_timezone();
			$display  = ( ETNB_Schedule::STATUS_ACTIVE === ETNB_Schedule::status( ETNB_Options::get_notice(), time(), $timezone ) );
		}

		/**
		 * お知らせを表示するかの最終判断。
		 *
		 * @param bool $display 表示するなら true。
		 */
		return (bool) apply_filters( 'etnb_should_display', $display );
	}

	/**
	 * スタイルを読み込む。表示するページでだけ読み込む。
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::should_display() ) {
			return;
		}

		// 版数をキャッシュバスターにする（プラグインを更新したら CSS も取り直させる）。
		$data = get_file_data( ETNB_PLUGIN_FILE, array( 'Version' => 'Version' ) );
		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/css/front.css', ETNB_PLUGIN_FILE ),
			array(),
			$data['Version']
		);
	}

	/**
	 * お知らせの HTML を出力する。
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! self::should_display() ) {
			return;
		}

		$notice = ETNB_Options::get_notice();

		echo self::markup( $notice, ETNB_Options::get_layout() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() の中で1値ずつエスケープ済み。

		// 掲載終了時刻が決まっていれば、ブラウザ側でも終了を判定する。
		$timezone = ETNB_Options::site_timezone();
		if ( null !== ETNB_Schedule::end_timestamp( $notice['end'], $timezone ) ) {
			self::print_expiry_script();
		}
	}

	/**
	 * お知らせの HTML を組み立てて返す。
	 *
	 * @param array $notice お知らせ（ETNB_Options::get_notice() の形）。
	 * @param array $layout 出し方（ETNB_Options::get_layout() の形）。
	 * @return string エスケープ済みの HTML。
	 */
	public static function markup( array $notice, array $layout ) {
		$timezone = ETNB_Options::site_timezone();
		$end      = ETNB_Schedule::end_timestamp( $notice['end'], $timezone );

		// 出し方をクラス名にする（例: etnb--pc-overlay etnb--sp-band）。値は sanitize_layout() で2値に絞ってある。
		$classes = array(
			'etnb',
			'etnb--pc-' . $layout['pc'],
			'etnb--sp-' . $layout['sp'],
		);

		/**
		 * 重ねるときの、直後の要素の上端からの余白（CSS の長さ。例: 8px / 1rem）。
		 *
		 * @param string $top 余白。
		 */
		$top = (string) apply_filters( 'etnb_overlay_top', self::DEFAULT_OVERLAY_TOP );
		// 数値＋単位の形だけを通す（style 属性に任意の文字列を入れさせない）。
		if ( ! preg_match( '/^\d+(\.\d+)?(px|rem|em|vw|vh|%)\z/', $top ) ) {
			$top = self::DEFAULT_OVERLAY_TOP;
		}

		$html = '<div id="etnb-notice" class="' . esc_attr( implode( ' ', $classes ) ) . '" style="' . esc_attr( '--etnb-overlay-top:' . $top ) . '"';

		// 終了時刻（ミリ秒）を埋め込む。ブラウザの Date.now() と比べるため。
		if ( null !== $end ) {
			$html .= ' data-etnb-end="' . esc_attr( (string) ( $end * 1000 ) ) . '"';
		}
		$html .= '>';

		$html .= '<div class="etnb__box" role="region" aria-label="' . esc_attr__( 'お知らせ', 'etbs-notice-banner' ) . '">';

		// 見出しは空なら出さない。
		if ( ! ETNB_Options::is_blank( $notice['heading'] ) ) {
			$html .= '<p class="etnb__heading">' . esc_html( $notice['heading'] ) . '</p>';
		}

		// 本文はエスケープしてから改行を <br> にする（順番を逆にすると <br> までエスケープされる）。
		$html .= '<p class="etnb__body">' . nl2br( esc_html( $notice['body'] ), false ) . '</p>';

		$html .= '</div></div>';

		return $html;
	}

	/**
	 * 掲載終了を過ぎていたらお知らせを取り除く、小さなスクリプトを出力する。
	 *
	 * お知らせの直後に置くので、画面に描かれる前に取り除ける（ちらつかない）。
	 *
	 * ★ 取り除くときは、このスクリプトのタグ自身も消す。
	 *   Lightning の固定ヘッダー（body.headfix）は、読み込み完了時に「ヘッダーのすぐ次の要素」へ
	 *   ヘッダーの高さぶんの上余白を付ける（lightning/js/_header_fixed.js の offset_header()）。
	 *   お知らせだけを消すと、次の要素がこのスクリプトのタグになり、上余白がそこに付いて効かず、
	 *   スライダーが固定ヘッダーの裏に潜る。タグも消せば、次の要素は本来どおりスライダーになる。
	 *
	 * @return void
	 */
	private static function print_expiry_script() {
		$js = '(function(){var s=document.currentScript,e=document.getElementById("etnb-notice");if(!e){return;}var t=parseInt(e.getAttribute("data-etnb-end"),10);if(t&&Date.now()>=t){e.parentNode.removeChild(e);if(s&&s.parentNode){s.parentNode.removeChild(s);}}})();';

		// WordPress 5.7 以降は、CSP の nonce 等を付けられる本体の関数で出す。
		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $js );
			return;
		}

		echo '<script>' . $js . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 固定の文字列で、利用者の入力を含まない。
	}
}
