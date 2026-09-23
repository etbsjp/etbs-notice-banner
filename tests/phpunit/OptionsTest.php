<?php
/**
 * inc/class-etnb-options.php（保存値の読み書きと入力値の検査）のテスト。
 *
 * @package etbs-notice-banner
 */

use PHPUnit\Framework\TestCase;

/**
 * ETNB_Options のテスト。
 */
class OptionsTest extends TestCase {

	/**
	 * テストごとにオプションストアを空にする。
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		etnb_test_reset();
	}

	/**
	 * ETNB_Options::sanitize_notice() のテスト。
	 *
	 * @return void
	 */
	public function test_sanitize_notice() {
		$test_cases = array(
			array(
				'test_condition_name' => '正しい入力 => そのまま・エラーなし',
				'input'               => array(
					'heading' => '休業日のお知らせ',
					'body'    => "2026年10月7日(水)\n10月13日(火)",
					'start'   => '2026-10-01',
					'end'     => '2026-10-13',
				),
				'expected'            => array(
					array(
						'heading' => '休業日のお知らせ',
						'body'    => "2026年10月7日(水)\n10月13日(火)",
						'start'   => '2026-10-01',
						'end'     => '2026-10-13',
					),
					array(),
				),
			),
			array(
				'test_condition_name' => 'タグ入り => タグを除く・見出しの改行は空白に',
				'input'               => array(
					'heading' => "<b>臨時</b>\n休業",
					'body'    => '<script>alert(1)</script>本文',
				),
				'expected'            => array(
					array(
						'heading' => '臨時 休業',
						'body'    => 'alert(1)本文',
						'start'   => '',
						'end'     => '',
					),
					array(),
				),
			),
			array(
				'test_condition_name' => '不正な日付 => 空にしてエラー',
				'input'               => array(
					'body'  => 'x',
					'start' => '2026-02-30',
					'end'   => '10/13',
				),
				'expected'            => array(
					array(
						'heading' => '',
						'body'    => 'x',
						'start'   => '',
						'end'     => '',
					),
					array( '掲載開始日の形式が正しくありません。', '掲載終了日の形式が正しくありません。' ),
				),
			),
			array(
				'test_condition_name' => '終了日が開始日より前 => エラー（値は残す）',
				'input'               => array(
					'body'  => 'x',
					'start' => '2026-10-13',
					'end'   => '2026-10-07',
				),
				'expected'            => array(
					array(
						'heading' => '',
						'body'    => 'x',
						'start'   => '2026-10-13',
						'end'     => '2026-10-07',
					),
					array( '掲載終了日が掲載開始日より前になっています。' ),
				),
			),
			array(
				'test_condition_name' => '開始日と終了日が同じ日 => エラーなし（1日だけ出す）',
				'input'               => array(
					'body'  => 'x',
					'start' => '2026-10-07',
					'end'   => '2026-10-07',
				),
				'expected'            => array(
					array(
						'heading' => '',
						'body'    => 'x',
						'start'   => '2026-10-07',
						'end'     => '2026-10-07',
					),
					array(),
				),
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], ETNB_Options::sanitize_notice( $case['input'] ), $case['test_condition_name'] );
		}
	}

	/**
	 * ETNB_Options::sanitize_layout() のテスト。
	 *
	 * @return void
	 */
	public function test_sanitize_layout() {
		$this->assertSame(
			array(
				'pc' => 'band',
				'sp' => 'overlay',
			),
			ETNB_Options::sanitize_layout(
				array(
					'pc' => 'band',
					'sp' => 'overlay',
				)
			),
			'許可された値 => そのまま'
		);
		$this->assertSame(
			array(
				'pc' => 'overlay',
				'sp' => 'band',
			),
			ETNB_Options::sanitize_layout(
				array(
					'pc' => '" onmouseover="x',
					'x'  => 'band',
				)
			),
			'不正な値・欠けたキー => 初期値（PC は重ねる・スマホは帯）'
		);
	}

	/**
	 * ETNB_Options::get_notice() のテスト。
	 *
	 * @return void
	 */
	public function test_get_notice() {
		// 未保存 => 初期値（見出しは「休業日のお知らせ」）。
		$this->assertSame(
			array(
				'heading' => '休業日のお知らせ',
				'body'    => '',
				'start'   => '',
				'end'     => '',
			),
			ETNB_Options::get_notice(),
			'未保存 => 初期値'
		);

		// 見出しを空で保存した => 空のまま（初期値で埋め戻さない）。余計なキーは捨てる。
		etnb_test_set_option(
			'etnb_notice',
			array(
				'heading' => '',
				'body'    => 'x',
				'extra'   => 'y',
			)
		);
		$this->assertSame(
			array(
				'heading' => '',
				'body'    => 'x',
				'start'   => '',
				'end'     => '',
			),
			ETNB_Options::get_notice(),
			'見出しを空で保存 => 空のまま'
		);

		// 保存値が壊れている（配列でない） => 初期値。
		etnb_test_set_option( 'etnb_notice', 'broken' );
		$this->assertSame( '休業日のお知らせ', ETNB_Options::get_notice()['heading'], '壊れた保存値 => 初期値' );
	}
}
