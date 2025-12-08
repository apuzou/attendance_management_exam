# COACHTECH 勤怠管理システム

## プロジェクト概要

Laravel 8.x で構築された勤怠管理システムです。ユーザー認証、出退勤打刻、休憩管理、修正申請機能、管理者機能を実装しています。

## 環境構築

### 必要な環境

- Docker
- Docker Compose
- Git

### セットアップ手順

```bash
# リポジトリのクローン
git clone git@github.com:apuzou/attendance_management_exam.git
cd attendance_management_exam

# Dockerコンテナのビルドと起動
docker-compose up -d --build

# PHPコンテナに入る
docker-compose exec php bash

# Composerで依存関係をインストール
composer install

# 環境変数ファイルの作成
cp .env.example .env

# アプリケーションキーの生成
php artisan key:generate

# データベースマイグレーション
php artisan migrate

# データベースシーディング（サンプルデータの投入）
php artisan db:seed
```

## 使用技術

### バックエンド

- PHP 8.1+
- Laravel 8.x
- MySQL 8.0.26

### インフラ

- Docker Compose
- nginx 1.21.1
- Mailhog (メールテスト用)

### 認証

- Laravel Fortify（認証機能の実装）
  - フォームリクエストによるバリデーション
  - カスタムログイン・会員登録処理
  - メール認証（確認コード入力方式）

## データベース構造

以下のテーブルが存在します：

- `users` - ユーザー情報（権限、部門コード含む）
- `attendances` - 勤怠情報
- `break_times` - 休憩時間情報
- `stamp_correction_requests` - 打刻修正申請情報
- `break_correction_requests` - 休憩時間修正申請情報

## ER 図

<!-- ER図を挿入 -->

## 主要機能

### 認証機能

- ユーザー登録
- ログイン/ログアウト
- メール認証（Mailhog 使用）
  - 確認コード入力方式
  - 未認証ユーザーは認証画面へリダイレクト

### 勤怠管理機能（一般ユーザー）

- **出退勤打刻**

  - 出勤打刻
  - 退勤打刻
  - 休憩開始/終了打刻
  - 現在の状態表示（勤務外、出勤中、休憩中、退勤済）

- **勤怠一覧**

  - 月別の勤怠一覧表示
  - 出勤日、出退勤時刻、休憩時間、勤務時間の表示
  - 月の切り替え機能

- **勤怠詳細**

  - 指定日の勤怠詳細表示
  - 打刻時間の修正申請機能
  - 休憩時間の修正申請機能
  - 修正申請履歴の表示

- **修正申請一覧**
  - 自分の修正申請一覧表示
  - 申請状態の確認（承認待ち、承認済み、却下）

### 管理者機能

- **管理者ログイン**

  - 管理者専用ログイン画面
  - 権限に応じたアクセス制御

- **勤怠一覧（管理者）**
  - 日別の全ユーザー勤怠一覧表示
  - アクセス権限に応じた表示制御
    - 全アクセス権限：全ユーザーの勤怠を表示
    - 部門アクセス権限：同じ部門のメンバーの勤怠のみ表示
  - 日付の切り替え機能（前日/翌日）

### 権限管理

- **全アクセス権限**（`role='admin'` かつ `department_code=1`）

  - 全ユーザーの勤怠を参照・修正・承認可能
  - 自身の承認も可能

- **部門アクセス権限**（`role='admin'` かつ `department_code!=1`）

  - 同じ部門のメンバーの勤怠を参照・修正可能
  - 自身の承認は不可

- **一般ユーザー**（`role='general'`）
  - 自身の勤怠のみ参照可能
  - 修正申請は可能

## アクセス URL

### アプリケーション

- ログイン画面: `http://localhost/login`
- ユーザー登録: `http://localhost/register`
- 勤怠打刻画面: `http://localhost/attendance`
- 勤怠一覧: `http://localhost/attendance/list`
- 修正申請一覧: `http://localhost/stamp_correction_request/list`

### 管理者画面

- 管理者ログイン: `http://localhost/admin/login`
- 管理者勤怠一覧: `http://localhost/admin/attendance/list`

### 管理ツール

- phpMyAdmin: `http://localhost:8080/` (ユーザー: laravel_user / パスワード: laravel_pass)
- Mailhog Web UI: `http://localhost:8025/`

## テストユーザー

### シーディング後のデフォルトユーザー

```bash
# シーディングを実行すると、以下のユーザーが作成されます

# フルアクセス権限
名前: 前田聖太
メールアドレス: maeda@example.com
パスワード: password123
権限: 全ユーザーの勤怠を参照・修正・承認可能

# 部門アクセス権限（管理者）
名前: 佐藤花子
メールアドレス: sato@example.com
パスワード: password123
権限: 同じ部門のメンバーの勤怠を参照・修正可能（部門コード: 2）

名前: 鈴木一郎
メールアドレス: suzuki@example.com
パスワード: password123
権限: 同じ部門のメンバーの勤怠を参照・修正可能（部門コード: 3）

# 一般ユーザー
名前: 田中太郎
メールアドレス: tanaka@example.com
パスワード: password123
部門コード: 2

名前: 山田花子
メールアドレス: yamada@example.com
パスワード: password123
部門コード: 2

名前: 高橋次郎
メールアドレス: takahashi@example.com
パスワード: password123
部門コード: 3

名前: 伊藤三郎
メールアドレス: ito@example.com
パスワード: password123
部門コード: 3
```

## トラブルシューティング

### Docker コンテナが起動しない

```bash
docker-compose down
docker-compose up -d --build
```

#### Apple Silicon(M1/M2)でイメージ起動に問題が出る場合

一部の公式イメージが arm64 で不安定な場合は、該当サービスに一時的に次の指定を追加してください。

```yml
platform: linux/amd64
```

### データベース接続エラー

- `.env` ファイルの `DB_HOST=mysql` を確認
- MySQL コンテナが起動しているか確認
- コンテナ名を確認: `docker-compose ps`

### メール送信ができない

- Mailhog コンテナが起動しているか確認
- `.env` の `MAIL_HOST=mailhog` を確認
- Mailhog Web UI (`http://localhost:8025/`) でメールを受信確認

### キャッシュクリア

```bash
php artisan view:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize:clear
```

### データベース操作

```bash
# マイグレーションのリセット
php artisan migrate:refresh --seed

# 特定のテーブルをリセット
php artisan migrate:refresh --path=database/migrations/2024_01_01_000002_create_attendances_table.php
```

## 開発時の注意点

### ファイル保存場所

- ロゴ画像: `storage/app/public/logo.svg`

### メール認証

- Mailhog を使用してローカル環境でテスト可能
- `http://localhost:8025/` でメールの確認が可能
- 確認コードは 6 桁の数字
- 初回登録時のみメール送信（2 重送信を防止）

### セッション管理

- 管理者ログイン状態はセッションで管理（`is_admin_login`）
- 通常ログインと管理者ログインで挙動が異なる

### CSS コンポーネント化

以下の共通コンポーネントが `public/css/components/` にあります：

- `container.css` - コンテナスタイル
- `table.css` - テーブルスタイル
- `link.css` - リンクスタイル
- `title.css` - タイトルスタイル
- `message.css` - メッセージスタイル
- `navigation.css` - ナビゲーションスタイル

### レスポンシブ対応

- デスクトップファーストのアプローチ
- メディアクエリ: `max-width: 1540px`, `1024px`, `768px`
- スタイルは各クラス定義にインラインで記述

