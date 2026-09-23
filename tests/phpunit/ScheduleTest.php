<?php
/**
 * inc/class-etnb-schedule.php（掲載期間の判定）のテスト。
 *
 * @package etbs-notice-banner
 */

use PHPUnit\Framework\TestCase;

/**
 * ETNB_Schedule のテスト。
 */
class ScheduleTest extends TestCase {

	/**
	 * ETNB_Schedule::is_valid_date() のテスト。
	 *
	 * @return void
	 */
	public function test_is_valid_date() {
		$test_cases = array(
			array(
				'test_condition_name' => '実在する日付 => true',
				'input'               => '2026-10-07',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'うるう年の2月29日 => true',
				'input'               => '2028-02-29',
				'expected'            => true,
			),
			array(
				'test_condition_name' => 'うるう年でない2月29日 => false',
				'input'               => '2026-02-29',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '存在しない月 => false',
				'input'               => '2026-13-01',
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'スラッシュ区切り => false',
				'input'               => '2026/10/07',
				'expected'            => false,
			),
			array(
				'test_condition_name' => 'ゼロ埋めなし => false',
				'input'               => '2026-10-7',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '空文字 => false',
				'input'               => '',
				'expected'            => false,
			),
			array(
				'test_condition_name' => '文字列でない => false',
				'input'               => null,
				'expected'            => false,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], ETNB_Schedule::is_valid_date( $case['input'] ), $case['test_condition_name'] );
		}
	}

	/**
	 * ETNB_Schedule::site_timezone() のテスト。2026-10-07 12:00 UTC 時点のオフセット（秒）で比べる。
	 *
	 * @return void
	 */
	public function test_site_timezone() {
		$test_cases = array(
			array(
				'test_condition_name' => '都市名 Asia/Tokyo => +9時間',
				'timezone_string'     => 'Asia/Tokyo',
				'gmt_offset'          => 0,
				'expected'            => 9 * 3600,
			),
			array(
				'test_condition_name' => '都市名が空・時差 9 => +9時間',
				'timezone_string'     => '',
				'gmt_offset'          => 9,
				'expected'            => 9 * 3600,
			),
			array(
				'test_condition_name' => '都市名が空・時差 -3.5（文字列） => -3時間30分',
				'timezone_string'     => '',
				'gmt_offset'          => '-3.5',
				'expected'            => -( 3 * 3600 + 30 * 60 ),
			),
			array(
				'test_condition_name' => '都市名が不正 => 時差の扱いへ落ちる',
				'timezone_string'     => 'Not/AZone',
				'gmt_offset'          => 5.75,
				'expected'            => 5 * 3600 + 45 * 60,
			),
		);

		$moment = new DateTimeImmutable( '2026-10-07 12:00:00', new DateTimeZone( 'UTC' ) );
		foreach ( $test_cases as $case ) {
			$tz = ETNB_Schedule::site_timezone( $case['timezone_string'], $case['gmt_offset'] );
			$this->assertSame( $case['expected'], $tz->getOffset( $moment ), $case['test_condition_name'] );
		}
	}

	/**
	 * ETNB_Schedule::start_timestamp() / end_timestamp() のテスト。
	 *
	 * @return void
	 */
	public function test_start_and_end_timestamp() {
		$tokyo = new DateTimeZone( 'Asia/Tokyo' );

		// 2026-10-07 0:00 JST = 2026-10-06 15:00 UTC。
		$this->assertSame( gmmktime( 15, 0, 0, 10, 6, 2026 ), ETNB_Schedule::start_timestamp( '2026-10-07', $tokyo ), '開始日 => その日の 0:00（JST）' );
		// 終了日 2026-10-13 => 翌 2026-10-14 0:00 JST = 2026-10-13 15:00 UTC。
		$this->assertSame( gmmktime( 15, 0, 0, 10, 13, 2026 ), ETNB_Schedule::end_timestamp( '2026-10-13', $tokyo ), '終了日 => 翌日の 0:00（JST）' );
		// 月末・年末をまたぐ。2026-12-31 => 2027-01-01 0:00 JST = 2026-12-31 15:00 UTC。
		$this->assertSame( gmmktime( 15, 0, 0, 12, 31, 2026 ), ETNB_Schedule::end_timestamp( '2026-12-31', $tokyo ), '年末 => 翌年1月1日の 0:00' );
		// 空・不正な日付は制限なし。
		$this->assertNull( ETNB_Schedule::start_timestamp( '', $tokyo ), '開始日が空 => null' );
		$this->assertNull( ETNB_Schedule::end_timestamp( '2026-02-30', $tokyo ), '終了日が不正 => null' );

		// 夏時間の終わる日（America/New_York は 2026-11-01 に UTC-4 から UTC-5 へ）でも、翌日 0:00 を暦で求める。
		// 2026-11-01 の翌日 2026-11-02 0:00 EST(UTC-5) = 2026-11-02 05:00 UTC。
		$ny = new DateTimeZone( 'America/New_York' );
		$this->assertSame( gmmktime( 5, 0, 0, 11, 2, 2026 ), ETNB_Schedule::end_timestamp( '2026-11-01', $ny ), '夏時間の終わる日 => 翌日の 0:00（EST）' );
	}

	/**
	 * ETNB_Schedule::status() のテスト。境目の前後1秒で確かめる。
	 *
	 * @return void
	 */
	public function test_status() {
		$tokyo  = new DateTimeZone( 'Asia/Tokyo' );
		$notice = array(
			'body'  => '2026年10月7日(水)・10月13日(火)',
			'start' => '2026-10-01',
			'end'   => '2026-10-13',
		);
		// 2026-10-01 0:00 JST = 2026-09-30 15:00 UTC。2026-10-14 0:00 JST = 2026-10-13 15:00 UTC。
		$start = gmmktime( 15, 0, 0, 9, 30, 2026 );
		$end   = gmmktime( 15, 0, 0, 10, 13, 2026 );

		$test_cases = array(
			array(
				'test_condition_name' => '開始の1秒前 => 開始前',
				'notice'              => $notice,
				'now'                 => $start - 1,
				'expected'            => ETNB_Schedule::STATUS_SCHEDULED,
			),
			array(
				'test_condition_name' => '開始ちょうど => 掲載中',
				'notice'              => $notice,
				'now'                 => $start,
				'expected'            => ETNB_Schedule::STATUS_ACTIVE,
			),
			array(
				'test_condition_name' => '終了日の 23:59:59 => 掲載中',
				'notice'              => $notice,
				'now'                 => $end - 1,
				'expected'            => ETNB_Schedule::STATUS_ACTIVE,
			),
			array(
				'test_condition_name' => '終了日の翌日 0:00 ちょうど => 終了',
				'notice'              => $notice,
				'now'                 => $end,
				'expected'            => ETNB_Schedule::STATUS_EXPIRED,
			),
			array(
				'test_condition_name' => '本文が空白だけ => 空（期間内でも出さない）',
				'notice'              => array_merge( $notice, array( 'body' => " \n " ) ),
				'now'                 => $start,
				'expected'            => ETNB_Schedule::STATUS_EMPTY,
			),
			array(
				'test_condition_name' => '開始日・終了日とも空 => 掲載中',
				'notice'              => array( 'body' => 'x' ),
				'now'                 => $end + 86400 * 365,
				'expected'            => ETNB_Schedule::STATUS_ACTIVE,
			),
		);

		foreach ( $test_cases as $case ) {
			$this->assertSame( $case['expected'], ETNB_Schedule::status( $case['notice'], $case['now'], $tokyo ), $case['test_condition_name'] );
		}
	}
}
