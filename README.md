# Kashiwazaki SEO Headline Generator

![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)

投稿の見出し構造を分析し、SEO最適化のための警告と提案を提供するWordPressプラグイン。階層構造バリデーション、文字数チェック、重複検出、サイト内カニバリチェック機能を搭載。さらに、自動目次生成機能も備えています。

## Features

### 見出し分析機能
- **見出し構造表示**: 投稿内の見出しを階層構造で視覚的に表示
- **階層構造バリデーション**: H2の次にH4が来るなどの階層飛びを検出
- **文字数チェック**: 長すぎる・短すぎる見出しを検出
- **重複検出**: 同一記事内で類似した見出しを検出
- **カニバリチェック**: 公開済み記事との類似コンテンツを検出
- **エクスポート機能**: 見出し構造をテキストまたはCSVで出力

### 目次生成機能
- **自動挿入**: 投稿・固定ページに目次を自動挿入
- **ショートコード対応**: `[kashiwazaki_toc]` で任意の位置に配置可能
- **カスタマイズ可能**: タイトル、番号表示、開閉ボタンなどを設定可能
- **スムーススクロール**: 固定ヘッダー対応のスクロール機能

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## Installation

1. プラグインファイルを `/wp-content/plugins/wp-plugin-kashiwazaki-seo-headline-generator` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 管理画面のサイドメニューに「Kashiwazaki SEO Headline Generator」が追加されます

## Usage

### 見出し分析
1. 投稿または固定ページの編集画面を開く
2. 「Kashiwazaki SEO Headline Generator」メタボックスを確認
3. 「分析する」ボタンをクリックして見出しを分析
4. 警告や提案を確認し、必要に応じて見出しを修正
5. 「カニバリチェック」で他の記事との重複をチェック

### 目次
目次は対象の投稿タイプで自動的に挿入されます。手動で配置する場合は以下のショートコードを使用:

```
[kashiwazaki_toc]
[kashiwazaki_toc title="この記事の内容"]
```

### 設定
管理画面 > Kashiwazaki SEO Headline Generator から以下の設定が可能:

- **見出し分析タブ**: 対象投稿タイプ、見出しレベル、文字数範囲、類似度閾値
- **目次タブ**: 自動挿入、挿入位置、表示設定、スクロール設定

## Author

柏崎剛 (Tsuyoshi Kashiwazaki)
- Website: https://www.tsuyoshikashiwazaki.jp
- Profile: https://www.tsuyoshikashiwazaki.jp/profile/

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.
```
