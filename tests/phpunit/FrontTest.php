<?php
/**
 * inc/class-etnb-front.php の markup()（お知らせの HTML）のテスト。
 *
 * @package etbs-notice-banner
 */

use PHPUnit\Framework\TestCase;

/**
 * ETNB_Front::markup() のテスト。
 */
class FrontTest extends TestCase {

	/**
	 * テストごとにオプション・フィルターを空にし、サイトのタイムゾーンを Asia/Tokyo にする。
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		etnb_test_reset();
		etnb_test_set_option( 'timezone_string', 'Asia/Tokyo' );
	}

	/**
	 * テスト用のお知らせを返す。
	 *
	 * @param array $overrides 上書きする値。
	 * @return array
	 */
	private function notice( array $overrides = array() ) {
		return array_merge(
			array(
				'heading' => '休業日のお知らせ',
				'body'    => '2026年10月7日(水)・10月13日(火)',
				'start'   => '',
				'end'     => '',
			),
			$overrides
		);
	}

	/**
	 * 出し方の既定値（PC は重ねる・スマホは帯）を返す。
	 *
	 * @return array
	 */
	private function layout() {
		return array(
			'pc' => 'overlay',
			'sp' => 'band',
		);
	}

	/**
	 * 見出し・本文・出し方のクラスが出ること。
	 *
	 * @return void
	 */
	public function test_basic_markup() {
		$html = ETNB_Front::markup( $this->notice(), $this->layout() );

		$this->assertStringContainsString( 'class="etnb etnb--pc-overlay etnb--sp-band"', $html, '出し方のクラス' );
		$this->assertStringContainsString( '<p class="etnb__heading">休業日のお知らせ</p>', $html, '見出し' );
		$this->assertStringContainsString( '<p class="etnb__body">2026年10月7日(水)・10月13日(火)</p>', $html, '本文' );
		$this->assertStringContainsString( 'style="--etnb-overlay-top:8px"', $html, '上余白の既定値' );
		$this->assertStringNotContainsString( 'data-etnb-end', $html, '終了日が空 => 終了時刻を埋め込まない' );
	}

	/**
	 * 本文の改行は <br> になり、HTML はエスケープされること（順番が逆だと <br> までエスケープされる）。
	 *
	 * @return void
	 */
	public function test_body_is_escaped_then_line_broken() {
		$html = ETNB_Front::markup( $this->notice( array( 'body' => "1行目<b>\n2行目" ) ), $this->layout() );

		$this->assertStringContainsString( '<p class="etnb__body">1行目&lt;b&gt;<br>' . "\n" . '2行目</p>', $html );
	}

	/**
	 * 見出しが空なら見出しの段落を出さないこと。
	 *
	 * @return void
	 */
	public function test_empty_heading_is_omitted() {
		$html = ETNB_Front::markup( $this->notice( array( 'heading' => " \u{3000} " ) ), $this->layout() );

		$this->assertStringNotContainsString( 'etnb__heading', $html );
	}

	/**
	 * 終了日があれば、翌日 0:00（JST）のミリ秒を埋め込むこと。
	 *
	 * @return void
	 */
	public function test_end_is_embedded_in_milliseconds() {
		$html = ETNB_Front::markup( $this->notice( array( 'end' => '2026-10-13' ) ), $this->layout() );

		// 2026-10-14 0:00 JST = 2026-10-13 15:00 UTC。
		$this->assertStringContainsString( 'data-etnb-end="' . ( gmmktime( 15, 0, 0, 10, 13, 2026 ) * 1000 ) . '"', $html );
	}

	/**
	 * 期限切れを取り除くスクリプトは、お知らせと一緒に自分のタグも消すこと。
	 *
	 * Lightning の固定ヘッダーは「ヘッダーのすぐ次の要素」に上余白を付ける。お知らせだけを消すと
	 * 次の要素がこのスクリプトのタグになり、スライダーが固定ヘッダーの裏に潜る（本番 bonshushu.com で確認）。
	 *
	 * @return void
	 */
	public function test_expiry_script_removes_its_own_tag() {
		$method = new ReflectionMethod( 'ETNB_Front', 'print_expiry_script' );
		// PHP 8.1 未満だけ必要（8.1 以降は常に呼べて、8.5 では呼ぶと非推奨の警告が出る）。
		if ( PHP_VERSION_ID < 80100 ) {
			$method->setAccessible( true );
		}

		ob_start();
		$method->invoke( null );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'document.currentScript', $html, '自分のタグを取得している' );
		$this->assertStringContainsString( 's.parentNode.removeChild(s)', $html, '自分のタグを消している' );
	}

	/**
	 * 上余白のフィルターは、数値＋単位の形だけを通すこと（style 属性に任意の文字列を入れさせない）。
	 *
	 * @return void
	 */
	public function test_overlay_top_filter_is_validated() {
		etnb_test_set_filter( 'etnb_overlay_top', '1.5rem' );
		$this->assertStringContainsString( 'style="--etnb-overlay-top:1.5rem"', ETNB_Front::markup( $this->notice(), $this->layout() ), '正しい値 => 通す' );

		etnb_test_set_filter( 'etnb_overlay_top', "12px\n" );
		$this->assertStringContainsString( 'style="--etnb-overlay-top:8px"', ETNB_Front::markup( $this->notice(), $this->layout() ), '末尾に改行 => 既定値' );

		etnb_test_set_filter( 'etnb_overlay_top', '8px;background:url(x)' );
		$this->assertStringContainsString( 'style="--etnb-overlay-top:8px"', ETNB_Front::markup( $this->notice(), $this->layout() ), '不正な値 => 既定値' );
	}
}
