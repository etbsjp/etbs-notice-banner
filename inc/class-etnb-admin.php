<?php
/**
 * 管理画面「お知らせバナー」。
 *
 * 編集者（edit_others_posts を持つ人）が、お知らせの見出し・本文・掲載期間を書き換える画面。
 * 同じ画面の下に、管理者（manage_options を持つ人）にだけ「出し方」の欄を出す。
 *
 * 保存は options.php ではなく admin-post.php で受ける。
 * options.php は既定で manage_options を要求するため、編集者が保存できないから。
 *
 * ★ My WP など、役割ごとに管理メニューを描き直すプラグインが入っているサイトでは、
 *   ここでメニューを足しても編集者の画面には出ない。そのサイトの My WP 側に項目の追加が要る。
 *
 * @package etbs-notice-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 管理画面をまとめたクラス。
 */
class ETNB_Admin {

	/**
	 * 画面のスラッグ（admin.php?page=… の値）。
	 */
	const PAGE = 'etbs-notice-banner';

	/**
	 * 保存処理の action 名（admin-post.php?action=…）。nonce の action も兼ねる。
	 */
	const ACTION = 'etnb_save';

	/**
	 * 画面を開ける・お知らせを保存できる権限。編集者と管理者が持つ。
	 */
	const CAP_EDIT = 'edit_others_posts';

	/**
	 * 出し方を保存できる権限。管理者だけが持つ。
	 */
	const CAP_LAYOUT = 'manage_options';

	/**
	 * 入力エラー時に、入力値とエラーを次の画面表示へ渡す一時データの接頭辞（後ろにユーザー ID を付ける）。
	 */
	const FORM_TRANSIENT = 'etnb_form_';

	/**
	 * フックを登録する。
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_save' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * トップレベルのメニュー「お知らせバナー」を足す。位置はダッシュボードの直下。
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'お知らせバナー', 'etbs-notice-banner' ),
			__( 'お知らせバナー', 'etbs-notice-banner' ),
			self::CAP_EDIT,
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-megaphone',
			3
		);
	}

	/**
	 * 画面を描く。
	 *
	 * @return void
	 */
	public static function render_page() {
		// メニューの権限と同じものを、描画の前にもう一度確かめる。
		if ( ! current_user_can( self::CAP_EDIT ) ) {
			wp_die( esc_html__( 'この画面を表示する権限がありません。', 'etbs-notice-banner' ) );
		}

		$notice = ETNB_Options::get_notice();
		$layout = ETNB_Options::get_layout();
		$errors = array();

		// 直前の保存が入力エラーだったら、そのとき入力した値とエラーを使う（入力が消えないように）。
		$transient_key = self::FORM_TRANSIENT . get_current_user_id();
		$form          = get_transient( $transient_key );
		if ( is_array( $form ) && isset( $form['notice'], $form['errors'] ) ) {
			$notice = array_merge( $notice, (array) $form['notice'] );
			$errors = (array) $form['errors'];
			delete_transient( $transient_key );
		}

		// 保存直後かどうか（リダイレクト先の URL に付けた印）。表示の出し分けにだけ使う。
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 表示の出し分けだけで、何も書き換えない。
		$saved = isset( $_GET['etnb_saved'] ) && '1' === $_GET['etnb_saved'];

		$timezone = ETNB_Schedule::site_timezone( get_option( 'timezone_string' ), get_option( 'gmt_offset' ) );
		$status   = ETNB_Schedule::status( ETNB_Options::get_notice(), time(), $timezone );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'お知らせバナー', 'etbs-notice-banner' ); ?></h1>

			<?php if ( $saved && empty( $errors ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '保存しました。', 'etbs-notice-banner' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( '保存していません。次の点を直してから、もう一度保存してください。', 'etbs-notice-banner' ); ?></p>
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'トップページの上部に表示するお知らせです。', 'etbs-notice-banner' ); ?></p>
			<p>
				<strong><?php esc_html_e( 'いまの状態：', 'etbs-notice-banner' ); ?></strong>
				<?php echo esc_html( self::status_label( $status ) ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="etnb-heading"><?php esc_html_e( '見出し', 'etbs-notice-banner' ); ?></label></th>
						<td>
							<input type="text" id="etnb-heading" name="etnb_notice[heading]" class="regular-text" value="<?php echo esc_attr( $notice['heading'] ); ?>">
							<p class="description"><?php esc_html_e( '例：休業日のお知らせ／臨時休業のお知らせ', 'etbs-notice-banner' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="etnb-body"><?php esc_html_e( '本文', 'etbs-notice-banner' ); ?></label></th>
						<td>
							<textarea id="etnb-body" name="etnb_notice[body]" class="large-text" rows="3"><?php echo esc_textarea( $notice['body'] ); ?></textarea>
							<p class="description"><?php esc_html_e( '例：2026年10月7日(水)・10月13日(火)', 'etbs-notice-banner' ); ?></p>
							<p class="description"><?php esc_html_e( '改行はそのまま表示されます。', 'etbs-notice-banner' ); ?></p>
							<p class="description"><?php esc_html_e( '本文を空にして保存すると、お知らせは表示されません。', 'etbs-notice-banner' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="etnb-start"><?php esc_html_e( '掲載開始日', 'etbs-notice-banner' ); ?></label></th>
						<td>
							<input type="date" id="etnb-start" name="etnb_notice[start]" value="<?php echo esc_attr( $notice['start'] ); ?>">
							<p class="description"><?php esc_html_e( 'この日の0時から表示します。空欄なら保存した時点から表示します。', 'etbs-notice-banner' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="etnb-end"><?php esc_html_e( '掲載終了日', 'etbs-notice-banner' ); ?></label></th>
						<td>
							<input type="date" id="etnb-end" name="etnb_notice[end]" value="<?php echo esc_attr( $notice['end'] ); ?>">
							<p class="description"><?php esc_html_e( 'この日の23時59分まで表示し、翌日から自動で表示しなくなります。空欄なら本文を消すまで表示し続けます。', 'etbs-notice-banner' ); ?></p>
						</td>
					</tr>
				</table>

				<?php if ( current_user_can( self::CAP_LAYOUT ) ) : ?>
					<h2><?php esc_html_e( '出し方（管理者だけに表示）', 'etbs-notice-banner' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						// PC とスマホの2行を同じ形で出す。
						$rows = array(
							'pc' => __( 'PC', 'etbs-notice-banner' ),
							'sp' => __( 'スマホ', 'etbs-notice-banner' ),
						);
						foreach ( $rows as $key => $label ) :
							?>
							<tr>
								<th scope="row"><?php echo esc_html( $label ); ?></th>
								<td>
									<fieldset>
										<legend class="screen-reader-text"><?php echo esc_html( $label ); ?></legend>
										<label><input type="radio" name="etnb_layout[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( ETNB_Options::MODE_OVERLAY ); ?>" <?php checked( $layout[ $key ], ETNB_Options::MODE_OVERLAY ); ?>> <?php esc_html_e( '下の画像に重ねる', 'etbs-notice-banner' ); ?></label><br>
										<label><input type="radio" name="etnb_layout[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( ETNB_Options::MODE_BAND ); ?>" <?php checked( $layout[ $key ], ETNB_Options::MODE_BAND ); ?>> <?php esc_html_e( '帯として置く（下の画像を押し下げる）', 'etbs-notice-banner' ); ?></label>
									</fieldset>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>

				<?php submit_button( __( '保存', 'etbs-notice-banner' ) ); ?>
			</form>

			<?php
			/**
			 * 画面の下に出す、反映の遅れについての注意書き。
			 * サイトのキャッシュの有効時間を実測したら、その値を入れた文に差し替える。
			 *
			 * @param string $note 注意書き（プレーンテキスト）。
			 */
			$cache_note = apply_filters( 'etnb_cache_note', __( 'サイトのキャッシュにより、保存してからトップページに反映されるまで数分かかることがあります。', 'etbs-notice-banner' ) );
			?>
			<p class="description"><?php echo esc_html( $cache_note ); ?></p>
		</div>
		<?php
	}

	/**
	 * 保存を受ける（admin-post.php?action=etnb_save）。
	 *
	 * @return void
	 */
	public static function handle_save() {
		// 権限と nonce（正規の画面から送られたことを確かめる使い捨ての合言葉）を確かめる。
		if ( ! current_user_can( self::CAP_EDIT ) ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'etbs-notice-banner' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		// お知らせの中身を検査する。
		$raw_notice = isset( $_POST['etnb_notice'] ) && is_array( $_POST['etnb_notice'] )
			? wp_unslash( $_POST['etnb_notice'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ETNB_Options::sanitize_notice() で1項目ずつ整える。
			: array();

		list( $notice, $errors ) = ETNB_Options::sanitize_notice( $raw_notice );

		$redirect = admin_url( 'admin.php?page=' . self::PAGE );

		// エラーがあれば何も保存せず、入力値とエラーを一時データに入れて画面へ戻す。
		if ( ! empty( $errors ) ) {
			set_transient(
				self::FORM_TRANSIENT . get_current_user_id(),
				array(
					'notice' => $notice,
					'errors' => $errors,
				),
				5 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		update_option( ETNB_Options::NOTICE, $notice );

		// 出し方は管理者から送られたときだけ保存する（編集者の送信に欄が混ざっていても無視する）。
		if ( current_user_can( self::CAP_LAYOUT ) && isset( $_POST['etnb_layout'] ) && is_array( $_POST['etnb_layout'] ) ) {
			$raw_layout = wp_unslash( $_POST['etnb_layout'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- ETNB_Options::sanitize_layout() で許可値だけにする。
			update_option( ETNB_Options::LAYOUT, ETNB_Options::sanitize_layout( $raw_layout ) );
		}

		wp_safe_redirect( add_query_arg( 'etnb_saved', '1', $redirect ) );
		exit;
	}

	/**
	 * 掲載状態を画面に出す文にする。
	 *
	 * @param string $status ETNB_Schedule::STATUS_* のいずれか。
	 * @return string 表示する文。
	 */
	public static function status_label( $status ) {
		switch ( $status ) {
			case ETNB_Schedule::STATUS_ACTIVE:
				return __( 'トップページに表示中です。', 'etbs-notice-banner' );
			case ETNB_Schedule::STATUS_SCHEDULED:
				return __( '掲載開始日になると表示されます。', 'etbs-notice-banner' );
			case ETNB_Schedule::STATUS_EXPIRED:
				return __( '掲載終了日を過ぎたため、表示していません。', 'etbs-notice-banner' );
			default:
				return __( '本文が空のため、表示していません。', 'etbs-notice-banner' );
		}
	}

	/**
	 * プラグイン一覧の自分の行に、支援・依頼のリンクを足す。
	 *
	 * 管理者しか見ない面なので、ここだけに置く（施設様が見るダッシュボードや画面のフッターには出さない）。
	 *
	 * @param string[] $links 既存のリンク。
	 * @param string   $file  その行のプラグイン（plugin_basename 形式）。
	 * @return string[] リンク。
	 */
	public static function plugin_row_meta( $links, $file ) {
		// 自分の行だけに足す。
		if ( plugin_basename( ETNB_PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		$utm     = '?utm_source=etbs-notice-banner&utm_medium=plugin';
		$links[] = '<a href="' . esc_url( 'https://etbs.jp/product/donate/' . $utm ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '開発を支援', 'etbs-notice-banner' ) . '</a>';
		$links[] = '<a href="' . esc_url( 'https://etbs.jp/product-category/wordpress-tools/' . $utm ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( '開発のご依頼', 'etbs-notice-banner' ) . '</a>';

		return $links;
	}
}
