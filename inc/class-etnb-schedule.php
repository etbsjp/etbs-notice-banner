<?php
/**
 * 掲載期間の判定。
 *
 * 掲載開始日・掲載終了日は「Y-m-d」の文字列で保存する（時刻は持たない）。
 * 開始日はその日の 0:00 から、終了日はその日の 23:59:59 まで（＝翌日 0:00 の直前まで）を掲載期間とする。
 * 日の境目はサイトのタイムゾーン（設定 → 一般）で決める。
 *
 * WordPress に依存しない純粋な処理だけを置き、PHPUnit で直接テストする。
 *
 * @package etbs-notice-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 掲載期間の判定をまとめたクラス。
 */
class ETNB_Schedule {

	/**
	 * 掲載状態：掲載中。
	 */
	const STATUS_ACTIVE = 'active';

	/**
	 * 掲載状態：掲載開始日の前。
	 */
	const STATUS_SCHEDULED = 'scheduled';

	/**
	 * 掲載状態：掲載終了日を過ぎた。
	 */
	const STATUS_EXPIRED = 'expired';

	/**
	 * 掲載状態：本文が空（何も表示しない）。
	 */
	const STATUS_EMPTY = 'empty';

	/**
	 * 「Y-m-d」形式の実在する日付かを判定する。
	 *
	 * input type="date" はブラウザによっては自由入力になるため、形式と暦の両方を確かめる。
	 *
	 * @param string $date 判定する文字列。
	 * @return bool 実在する日付なら true。
	 */
	public static function is_valid_date( $date ) {
		// 形式（4桁-2桁-2桁）を確かめる。
		if ( ! is_string( $date ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
			return false;
		}

		// 2月30日のような存在しない日付を弾く。
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * サイトの設定値からタイムゾーンを作る。
	 *
	 * wp_timezone()（WordPress 5.3 以降）と同じ考え方を、WordPress に依存せずに書いたもの。
	 * このプラグインは WordPress の下限を宣言しないため、新しい関数に頼らない。
	 *
	 * @param string       $timezone_string 「設定 → 一般」の都市名（例: Asia/Tokyo）。UTC±n を選んだサイトでは空。
	 * @param float|string $gmt_offset      UTC からの時差（時間単位。例: 9 / -3.5）。
	 * @return DateTimeZone サイトのタイムゾーン。
	 */
	public static function site_timezone( $timezone_string, $gmt_offset ) {
		// 都市名が入っていればそれを使う（夏時間の切り替えも正しく扱える）。
		if ( is_string( $timezone_string ) && '' !== $timezone_string ) {
			try {
				return new DateTimeZone( $timezone_string );
			} catch ( Exception $e ) {
				// 不正な値なら下の時差の扱いへ落とす。
				unset( $e );
			}
		}

		// 時差（時間）を「+09:00」の形に直す。小数（-3.5 など）は分に直す。
		$offset  = (float) $gmt_offset;
		$sign    = ( $offset < 0 ) ? '-' : '+';
		$abs_min = (int) round( abs( $offset ) * 60 );

		return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, intdiv( $abs_min, 60 ), $abs_min % 60 ) );
	}

	/**
	 * 掲載開始の時刻（Unix 時刻）を返す。開始日のサイト時刻 0:00。
	 *
	 * @param string       $start_date 掲載開始日（Y-m-d）。空なら制限なし。
	 * @param DateTimeZone $timezone   サイトのタイムゾーン。
	 * @return int|null 開始の Unix 時刻。制限なしなら null。
	 */
	public static function start_timestamp( $start_date, DateTimeZone $timezone ) {
		// 空・不正な日付は「制限なし」として扱う。
		if ( ! self::is_valid_date( $start_date ) ) {
			return null;
		}

		// サイトのタイムゾーンでその日の 0:00 を作る。
		$start = new DateTimeImmutable( $start_date . ' 00:00:00', $timezone );

		return $start->getTimestamp();
	}

	/**
	 * 掲載終了の時刻（Unix 時刻）を返す。終了日の翌日のサイト時刻 0:00（この瞬間から表示しない）。
	 *
	 * 「23:59:59」ではなく翌日 0:00 を境目にするのは、秒の端数で1秒だけ抜けるのを避けるため。
	 * 夏時間の切り替え日でも、翌日の 0:00 を暦で求めるので長さのずれは起きない。
	 *
	 * @param string       $end_date 掲載終了日（Y-m-d）。空なら制限なし。
	 * @param DateTimeZone $timezone サイトのタイムゾーン。
	 * @return int|null 終了の Unix 時刻（この時刻以降は表示しない）。制限なしなら null。
	 */
	public static function end_timestamp( $end_date, DateTimeZone $timezone ) {
		// 空・不正な日付は「制限なし」として扱う。
		if ( ! self::is_valid_date( $end_date ) ) {
			return null;
		}

		// 終了日の 0:00 を作り、暦で1日進める。
		$end = new DateTimeImmutable( $end_date . ' 00:00:00', $timezone );

		return $end->modify( '+1 day' )->getTimestamp();
	}

	/**
	 * いまの掲載状態を返す。
	 *
	 * @param array        $notice   お知らせ（'body' / 'start' / 'end' を使う）。
	 * @param int          $now      現在の Unix 時刻。
	 * @param DateTimeZone $timezone サイトのタイムゾーン。
	 * @return string STATUS_* のいずれか。
	 */
	public static function status( array $notice, $now, DateTimeZone $timezone ) {
		// 本文が空なら、見出しが入っていても表示しない（見出しだけでは何のお知らせか分からないため）。
		$body = isset( $notice['body'] ) ? trim( (string) $notice['body'] ) : '';
		if ( '' === $body ) {
			return self::STATUS_EMPTY;
		}

		// 開始前か。
		$start = self::start_timestamp( isset( $notice['start'] ) ? $notice['start'] : '', $timezone );
		if ( null !== $start && $now < $start ) {
			return self::STATUS_SCHEDULED;
		}

		// 終了後か（終了時刻ちょうどは終了扱い）。
		$end = self::end_timestamp( isset( $notice['end'] ) ? $notice['end'] : '', $timezone );
		if ( null !== $end && $now >= $end ) {
			return self::STATUS_EXPIRED;
		}

		return self::STATUS_ACTIVE;
	}
}
