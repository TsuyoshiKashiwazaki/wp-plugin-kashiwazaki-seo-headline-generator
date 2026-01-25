# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-25

### Added
- 見出し分析機能
  - 見出し構造の視覚的表示
  - 階層構造バリデーション（階層飛び検出）
  - 文字数チェック（推奨範囲外の警告）
  - 記事内の見出し重複検出
- サイト内カニバリチェック機能
  - 公開済み記事との類似コンテンツ検出
  - タイトル・見出しの類似度判定
- 自動目次生成機能
  - 投稿・固定ページへの自動挿入
  - 挿入位置の選択（最初の見出し前/段落後/先頭）
  - 最小見出し数の設定
- ショートコード `[kashiwazaki_toc]` 対応
  - タイトルのカスタマイズ
- 目次表示オプション
  - 番号表示（1, 1.1, 1.2... 形式）
  - 開閉ボタン表示
  - デフォルト開閉状態の設定
- スムーススクロール機能
  - 固定ヘッダーの自動検出
  - スクロールオフセットの設定
- エクスポート機能
  - テキスト形式でコピー
  - CSV形式でコピー/ダウンロード
- 管理画面
  - 見出し分析設定タブ
  - 目次設定タブ
  - 使い方タブ

[1.0.0]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-headline-generator/releases/tag/v1.0.0-dev
