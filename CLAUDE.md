# etbs-notice-banner

etbs が配布する WordPress プラグイン。共通ルールの正本は `~/.claude/etbs-plugin-rules.md`。
起票・設計の経緯は task-queue #335（決定事項15件の表が正本）。

## レビュー工程に大（シニアエンジニア）を追加する

このリポジトリでは、安藤（`vk-code-reviewer`）のレビューのあと、**PR を作成する前に**
大（`etbs-senior-wp`）の監査を必ず通すこと。大は etbs の申し送りと過去に踏んだ罠に照らして
「リリースできる形になっているか」を見る担当で、安藤の一般的なコード品質レビューとは層が違う。

- `Agent` ツールで `subagent_type: etbs-senior-wp`、`name: etbs-senior-wp`、
  **`run_in_background: false`** で起動する
- **`isolation: "worktree"` は使えるなら付ける**（付けないと起動応答は「成功」と返るのに
  一度も作業せず待機状態に入ることがある）。ただし ★★ **作業ディレクトリが git リポジトリでないと使えない。**
  その場合は **isolation なしで起動してよい**。**見分け方は起動応答の形**——`output_file` 付きの正常形なら動いている
- prompt には対象リポジトリ・ブランチ・差分（または PR 番号）を渡す
- 大には **出力の末尾に `監査結果: PASS` または `監査結果: FAIL` を必ず書くよう指示する**
  （★ 大の定義ファイルには出力形式の指定が無いため、指示しないと合否を機械判定できない）
- `監査結果: PASS` を受け取るまで PR を作成しない。`FAIL` なら和田へ差し戻して再監査する

★ 大は vk-agents のメンバー表に登録されていないため、指示が無いと**永久に呼ばれない**。

## 導入先

| サイト | 設定 | 備考 |
|---|---|---|
| bonshushu.com（XSERVER `sv1509`） | PC：重ねる／スマホ：帯 | 子テーマ `bonshushu` の `header.php` がスライダー直前で `lightning_header_after` を呼ぶ。**My WP で編集者のメニューに「お知らせバナー」を手で追加している** |

★ bonshushu.com は task-queue #61（Lightning G3 化）の対象。G3 化のときは差し込み口の有無と位置を確認し、
変わっていれば `etnb_output_hook` フィルターで差し替える。

## 検証環境

Local の `ai-wp-demo`（`aiwpdemo.etbs.lc`）。**シンボリックリンク設置でよい**
（`dirname( __FILE__, N )` を使っていない）。テーマが Lightning ではないため、
`wp-content/mu-plugins/etnb-local-test.php` で差し込み口を `wp_body_open` に差し替えて確かめる。

- ★ Browser ペインは `.lc` の外部 CSS・JS を読み込めない（テーマ自身の CSS も `cssRules` が読めない）。
  見た目は CSS をページに直接差し込んで測るか、本番（https）で確かめる
- CLI 検証では Local の php.ini を `-c` で渡すこと

## テスト

`composer install` のあと `vendor/bin/phpunit`。WordPress のテストスイートは使わず、
`tests/phpunit/bootstrap.php` で必要な関数だけをスタブにして、掲載期間の判定・入力値の検査・HTML の組み立てを確かめる。
管理画面の保存（権限・nonce・入力エラー時の戻し）と表示は検証サイトで確かめて PR に記録する。

## アンインストール

★ `uninstall.php` の方針は**案A**（task-queue #108）。

- **残す** … `etnb_notice`（利用者が書いたお知らせ）・`etnb_layout`（管理者が設定した出し方）
- **消す** … 該当なし。一時データ `etnb_form_<ユーザー ID>` は5分で期限が切れる。cron・独自テーブルは持たない

## 支援・依頼リンク

**プラグイン一覧の行（`plugin_row_meta`）だけに置き、ダッシュボードと画面のフッターには置かない。**
共通ルールの「サポート導線3面」から意図的に外している。このプラグインの画面を開くのは受託先の施設様（編集者）で、
そこに「開発を支援」を出すのは筋違いのため。プラグイン一覧は管理者（弊社）しか見ない。

## 版数

版数は**ヘッダの `Version:` 1箇所のみ**。CSS のキャッシュバスターは `get_file_data()` でヘッダから読むので、別に書かない。
`readme.txt` は無く `README.md` のみなので、PUC は本体ヘッダを読む。

## CI

**共通ルールは `~/.claude/etbs-plugin-rules.md` の 2.7 節**。ここにはこの repo でしか決まらない値だけを置く。

- 既存指摘の基準値: **0 ERROR / 0 WARNING**（初版・`WordPress-Extra`・`tests/` と同梱 PUC は除外）
- **`Requires PHP` は無宣言。** CI の matrix は `['7.4','8.3']` なので **7.3 は CI では守られていない**。
  初版は PHP 7.3.5 の `php -l` を手元で通している（`intdiv()` は 7.0 以降）
- **`Requires at least` も無宣言。** 自前コードで最も新しい API は `sanitize_textarea_field()`（WP 4.7）、
  同梱 PUC は `wp_doing_cron()`（WP 4.8）。`wp_print_inline_script_tag()`（WP 5.7）は `function_exists()` で分岐している
- `composer.json` は etbs-account-guard と同じく PHPUnit 9.6 と `config.platform.php = 7.3.0` を足している。
  **原本（widget-shortcode-tools）の composer 一式で上書きしないこと**
