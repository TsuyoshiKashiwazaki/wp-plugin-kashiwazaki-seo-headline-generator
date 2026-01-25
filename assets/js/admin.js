/**
 * Kashiwazaki SEO Headline Generator - Admin JavaScript
 */

(function($) {
    'use strict';

    var KashiwazakiSeoHeadline = {
        /**
         * 現在の分析結果を保存
         */
        currentAnalysis: null,

        /**
         * 初期化
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * イベントをバインド
         */
        bindEvents: function() {
            var self = this;

            // 分析ボタン
            $(document).on('click', '#kashiwazaki-seo-headline-analyze', function(e) {
                e.preventDefault();
                self.analyzeHeadlines();
            });

            // カニバリチェックボタン
            $(document).on('click', '#kashiwazaki-seo-headline-check-cannibalization', function(e) {
                e.preventDefault();
                self.checkCannibalization();
            });

            // テキストコピーボタン
            $(document).on('click', '#kashiwazaki-seo-headline-copy-text', function(e) {
                e.preventDefault();
                self.copyAsText();
            });

            // CSVコピーボタン
            $(document).on('click', '#kashiwazaki-seo-headline-copy-csv', function(e) {
                e.preventDefault();
                self.copyAsCsv();
            });

            // CSVダウンロードボタン
            $(document).on('click', '#kashiwazaki-seo-headline-download-csv', function(e) {
                e.preventDefault();
                self.downloadCsv();
            });

            // 投稿タイプ全選択ボタン
            $(document).on('click', '#kashiwazaki-post-types-select-all', function(e) {
                e.preventDefault();
                $('.kashiwazaki-post-type-checkbox').prop('checked', true);
            });

            // 投稿タイプ全解除ボタン
            $(document).on('click', '#kashiwazaki-post-types-deselect-all', function(e) {
                e.preventDefault();
                $('.kashiwazaki-post-type-checkbox').prop('checked', false);
            });

            // 目次対象投稿タイプ全選択ボタン
            $(document).on('click', '#kashiwazaki-toc-post-types-select-all', function(e) {
                e.preventDefault();
                $('.kashiwazaki-toc-post-type-checkbox').prop('checked', true);
            });

            // 目次対象投稿タイプ全解除ボタン
            $(document).on('click', '#kashiwazaki-toc-post-types-deselect-all', function(e) {
                e.preventDefault();
                $('.kashiwazaki-toc-post-type-checkbox').prop('checked', false);
            });
        },

        /**
         * 投稿コンテンツを取得
         */
        getPostContent: function() {
            var content = '';

            // ブロックエディタ（Gutenberg）の場合
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                content = wp.data.select('core/editor').getEditedPostContent();
            }
            // クラシックエディタの場合
            else if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                content = tinymce.get('content').getContent();
            }
            // テキストモードの場合
            else if ($('#content').length) {
                content = $('#content').val();
            }

            return content;
        },

        /**
         * 投稿タイトルを取得
         */
        getPostTitle: function() {
            var title = '';

            // ブロックエディタの場合
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                title = wp.data.select('core/editor').getEditedPostAttribute('title');
            }
            // クラシックエディタの場合
            else if ($('#title').length) {
                title = $('#title').val();
            }

            return title;
        },

        /**
         * 投稿IDを取得
         */
        getPostId: function() {
            var postId = 0;

            // ブロックエディタの場合
            if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
                postId = wp.data.select('core/editor').getCurrentPostId();
            }
            // クラシックエディタの場合
            else if ($('#post_ID').length) {
                postId = parseInt($('#post_ID').val(), 10);
            }

            return postId || kashiwazakiSeoHeadline.postId || 0;
        },

        /**
         * 見出しを分析
         */
        analyzeHeadlines: function() {
            var self = this;
            var content = this.getPostContent();
            var title = this.getPostTitle();
            var postId = this.getPostId();

            this.showLoading();

            $.ajax({
                url: kashiwazakiSeoHeadline.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kashiwazaki_seo_headline_analyze',
                    nonce: kashiwazakiSeoHeadline.nonce,
                    content: content,
                    title: title,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        self.currentAnalysis = response.data;
                        self.displayResults(response.data);
                    } else {
                        self.showError(response.data.message || 'エラーが発生しました。');
                    }
                },
                error: function() {
                    self.showError('通信エラーが発生しました。');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        /**
         * カニバリゼーションをチェック
         */
        checkCannibalization: function() {
            var self = this;

            if (!this.currentAnalysis || !this.currentAnalysis.headlines) {
                alert('まず「分析する」ボタンで見出しを分析してください。');
                return;
            }

            var headlines = this.currentAnalysis.headlines.map(function(h) {
                return h.text;
            });
            var title = this.getPostTitle();
            var postId = this.getPostId();

            this.showLoading();

            $.ajax({
                url: kashiwazakiSeoHeadline.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'kashiwazaki_seo_headline_check_cannibalization',
                    nonce: kashiwazakiSeoHeadline.nonce,
                    headlines: headlines,
                    title: title,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        self.displayCannibalizationResults(response.data);
                    } else {
                        self.showError(response.data.message || 'エラーが発生しました。');
                    }
                },
                error: function() {
                    self.showError('通信エラーが発生しました。');
                },
                complete: function() {
                    self.hideLoading();
                }
            });
        },

        /**
         * 結果を表示
         */
        displayResults: function(data) {
            var $results = $('.kashiwazaki-seo-headline-results');
            var $initialMessage = $('.kashiwazaki-seo-headline-initial-message');
            var $stats = $('.kashiwazaki-seo-headline-stats');
            var $exportButtons = $('.kashiwazaki-seo-headline-export-buttons');

            // 初期メッセージを非表示
            $initialMessage.hide();

            // 見出しがない場合
            if (!data.headlines || data.headlines.length === 0) {
                $results.hide();
                $stats.hide();
                $exportButtons.hide();
                $initialMessage.html('<p>' + kashiwazakiSeoHeadline.i18n.noHeadlines + '</p>').show();
                $('#kashiwazaki-seo-headline-check-cannibalization').prop('disabled', true);
                return;
            }

            // 統計情報を更新
            var warningCount = (data.hierarchy_warnings ? data.hierarchy_warnings.length : 0) +
                              (data.length_warnings ? data.length_warnings.length : 0) +
                              (data.duplicate_warnings ? data.duplicate_warnings.length : 0);

            $('#kashiwazaki-seo-headline-count').text(data.total_count);
            $('#kashiwazaki-seo-headline-warning-count').text(warningCount);
            $stats.show();

            // 見出し構造を表示
            this.displayStructure(data.headlines, data.hierarchy_warnings, data.length_warnings);

            // 階層警告を表示
            this.displayHierarchyWarnings(data.hierarchy_warnings);

            // 文字数警告を表示
            this.displayLengthWarnings(data.length_warnings);

            // 重複警告を表示
            this.displayDuplicateWarnings(data.duplicate_warnings);

            // カニバリゼーション警告をリセット
            $('.kashiwazaki-seo-headline-cannibalization-warnings').hide();
            $('#kashiwazaki-seo-headline-cannibalization-list').empty();

            // 結果とエクスポートボタンを表示
            $results.show();
            $exportButtons.show();

            // カニバリチェックボタンを有効化
            $('#kashiwazaki-seo-headline-check-cannibalization').prop('disabled', false);
        },

        /**
         * 見出し構造を表示
         */
        displayStructure: function(headlines, hierarchyWarnings, lengthWarnings) {
            var $container = $('#kashiwazaki-seo-headline-structure');
            var html = '<ul class="kashiwazaki-seo-headline-structure-list">';
            var options = kashiwazakiSeoHeadline.options;

            // 警告のあるインデックスをセットに追加
            var hierarchyWarningIndices = new Set();
            var lengthWarningIndices = new Set();

            if (hierarchyWarnings) {
                hierarchyWarnings.forEach(function(w) {
                    hierarchyWarningIndices.add(w.index);
                });
            }

            if (lengthWarnings) {
                lengthWarnings.forEach(function(w) {
                    lengthWarningIndices.add(w.index);
                });
            }

            headlines.forEach(function(headline) {
                var hasHierarchyWarning = hierarchyWarningIndices.has(headline.index);
                var hasLengthWarning = lengthWarningIndices.has(headline.index);
                var warningClass = '';
                var charCountClass = '';

                if (hasHierarchyWarning) {
                    warningClass = ' has-error';
                } else if (hasLengthWarning) {
                    warningClass = ' has-warning';
                }

                if (headline.char_count < options.min_length) {
                    charCountClass = ' error';
                } else if (headline.char_count > options.max_length) {
                    charCountClass = ' warning';
                }

                html += '<li class="level-' + headline.level + warningClass + '">';
                html += '<span class="headline-tag">' + headline.tag.toUpperCase() + '</span>';
                html += '<span class="headline-text">' + this.escapeHtml(headline.text) + '</span>';
                html += '<span class="headline-char-count' + charCountClass + '">' + headline.char_count + kashiwazakiSeoHeadline.i18n.characters + '</span>';
                html += '</li>';
            }.bind(this));

            html += '</ul>';
            $container.html(html);
        },

        /**
         * 階層警告を表示
         */
        displayHierarchyWarnings: function(warnings) {
            var $section = $('.kashiwazaki-seo-headline-hierarchy-warnings');
            var $container = $('#kashiwazaki-seo-headline-hierarchy-list');

            if (!warnings || warnings.length === 0) {
                $section.hide();
                return;
            }

            var html = '<ul class="kashiwazaki-seo-headline-warning-list">';

            warnings.forEach(function(warning) {
                html += '<li class="error">';
                html += '<span class="warning-message">' + kashiwazakiSeoHeadline.i18n.hierarchyWarning + '</span>';
                html += '<span class="warning-detail">' + this.escapeHtml(warning.message) + '</span>';
                html += '<div class="warning-items">';
                html += '<div class="item">';
                html += '<span class="headline-tag">' + warning.previous.tag.toUpperCase() + '</span>';
                html += '<span>' + this.escapeHtml(warning.previous.text) + '</span>';
                html += '</div>';
                html += '<div class="item" style="color: #dc3232;">';
                html += '<span class="headline-tag" style="background: #dc3232;">' + warning.current.tag.toUpperCase() + '</span>';
                html += '<span>' + this.escapeHtml(warning.current.text) + '</span>';
                html += '</div>';
                html += '</div>';
                html += '</li>';
            }.bind(this));

            html += '</ul>';
            $container.html(html);
            $section.show();
        },

        /**
         * 文字数警告を表示
         */
        displayLengthWarnings: function(warnings) {
            var $section = $('.kashiwazaki-seo-headline-length-warnings');
            var $container = $('#kashiwazaki-seo-headline-length-list');

            if (!warnings || warnings.length === 0) {
                $section.hide();
                return;
            }

            var html = '<ul class="kashiwazaki-seo-headline-warning-list">';

            warnings.forEach(function(warning) {
                var headline = warning.headline;
                warning.issues.forEach(function(issue) {
                    var liClass = issue.type === 'too_short' ? 'error' : '';

                    html += '<li class="' + liClass + '">';
                    html += '<span class="warning-message">' + kashiwazakiSeoHeadline.i18n.lengthWarning + '</span>';
                    html += '<span class="warning-detail">' + this.escapeHtml(issue.message) + '</span>';
                    html += '<div class="warning-items">';
                    html += '<div class="item">';
                    html += '<span class="headline-tag">' + headline.tag.toUpperCase() + '</span>';
                    html += '<span>' + this.escapeHtml(headline.text) + '</span>';
                    html += '</div>';
                    html += '</div>';
                    html += '</li>';
                }.bind(this));
            }.bind(this));

            html += '</ul>';
            $container.html(html);
            $section.show();
        },

        /**
         * 重複警告を表示
         */
        displayDuplicateWarnings: function(warnings) {
            var $section = $('.kashiwazaki-seo-headline-duplicate-warnings');
            var $container = $('#kashiwazaki-seo-headline-duplicate-list');

            if (!warnings || warnings.length === 0) {
                $section.hide();
                return;
            }

            var html = '<ul class="kashiwazaki-seo-headline-warning-list">';

            warnings.forEach(function(warning) {
                html += '<li>';
                html += '<span class="warning-message">' + kashiwazakiSeoHeadline.i18n.duplicateWarning + '</span>';
                html += '<span class="warning-detail">' + kashiwazakiSeoHeadline.i18n.similarity + ': ' + warning.similarity + '%</span>';
                html += '<div class="warning-items">';
                html += '<div class="item">';
                if (warning.item1.tag === 'title') {
                    html += '<span class="headline-tag" style="background: #826eb4;">タイトル</span>';
                } else {
                    html += '<span class="headline-tag">' + warning.item1.tag.toUpperCase() + '</span>';
                }
                html += '<span>' + this.escapeHtml(warning.item1.text) + '</span>';
                html += '</div>';
                html += '<div class="item">';
                if (warning.item2.tag === 'title') {
                    html += '<span class="headline-tag" style="background: #826eb4;">タイトル</span>';
                } else {
                    html += '<span class="headline-tag">' + warning.item2.tag.toUpperCase() + '</span>';
                }
                html += '<span>' + this.escapeHtml(warning.item2.text) + '</span>';
                html += '</div>';
                html += '</div>';
                html += '</li>';
            }.bind(this));

            html += '</ul>';
            $container.html(html);
            $section.show();
        },

        /**
         * カニバリゼーション結果を表示
         */
        displayCannibalizationResults: function(warnings) {
            var $section = $('.kashiwazaki-seo-headline-cannibalization-warnings');
            var $container = $('#kashiwazaki-seo-headline-cannibalization-list');

            if (!warnings || warnings.length === 0) {
                $container.html('<div class="kashiwazaki-seo-headline-success">サイト内で類似コンテンツは検出されませんでした。</div>');
                $section.show();
                return;
            }

            var html = '';

            warnings.forEach(function(warning) {
                html += '<div class="cannibalization-item">';
                html += '<div class="cannibalization-current">';
                html += '<span class="label">現在の' + (warning.current_type === 'title' ? 'タイトル' : '見出し') + ':</span>';
                html += '<span class="text">' + this.escapeHtml(warning.current_text) + '</span>';
                html += '</div>';
                html += '<div class="cannibalization-matched">';
                html += '<span class="label">類似コンテンツが見つかりました:</span>';
                html += '<div class="post-title">' + this.escapeHtml(warning.matched_title) + '</div>';
                html += '<div class="matched-text">';
                html += (warning.matched_type === 'title' ? 'タイトル' : '見出し') + ': ' + this.escapeHtml(warning.matched_text);
                html += '</div>';
                html += '<span class="similarity">' + kashiwazakiSeoHeadline.i18n.similarity + ' ' + warning.similarity + '%</span>';
                if (warning.edit_link) {
                    html += '<a href="' + warning.edit_link + '" target="_blank" class="edit-link">' + kashiwazakiSeoHeadline.i18n.editPost + ' →</a>';
                }
                html += '</div>';
                html += '</div>';
            }.bind(this));

            $container.html(html);
            $section.show();

            // 警告カウントを更新
            var currentWarningCount = parseInt($('#kashiwazaki-seo-headline-warning-count').text(), 10) || 0;
            $('#kashiwazaki-seo-headline-warning-count').text(currentWarningCount + warnings.length);
        },

        /**
         * テキストとしてコピー
         */
        copyAsText: function() {
            if (!this.currentAnalysis || !this.currentAnalysis.headlines) {
                return;
            }

            var text = '';
            var title = this.getPostTitle();

            if (title) {
                text += 'タイトル: ' + title + '\n\n';
            }

            text += '見出し構造:\n';

            this.currentAnalysis.headlines.forEach(function(headline) {
                var indent = '  '.repeat(headline.level - 1);
                text += indent + headline.tag.toUpperCase() + ': ' + headline.text + ' (' + headline.char_count + '文字)\n';
            });

            this.copyToClipboard(text);
        },

        /**
         * CSVとしてコピー
         */
        copyAsCsv: function() {
            var csv = this.generateCsv();
            this.copyToClipboard(csv);
        },

        /**
         * CSVをダウンロード
         */
        downloadCsv: function() {
            var csv = this.generateCsv();
            var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);

            var title = this.getPostTitle() || 'headlines';
            var filename = title.substring(0, 30).replace(/[^a-zA-Z0-9\u3040-\u309F\u30A0-\u30FF\u4E00-\u9FAF]/g, '_') + '_headlines.csv';

            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        /**
         * CSVを生成
         */
        generateCsv: function() {
            if (!this.currentAnalysis || !this.currentAnalysis.headlines) {
                return '';
            }

            var rows = [];
            var title = this.getPostTitle();

            // ヘッダー行
            rows.push(['レベル', '見出しタグ', '見出しテキスト', '文字数'].join(','));

            // タイトル行
            if (title) {
                rows.push([
                    '0',
                    'タイトル',
                    '"' + title.replace(/"/g, '""') + '"',
                    title.length
                ].join(','));
            }

            // 見出し行
            this.currentAnalysis.headlines.forEach(function(headline) {
                rows.push([
                    headline.level,
                    headline.tag.toUpperCase(),
                    '"' + headline.text.replace(/"/g, '""') + '"',
                    headline.char_count
                ].join(','));
            });

            return rows.join('\n');
        },

        /**
         * クリップボードにコピー
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    alert(kashiwazakiSeoHeadline.i18n.copySuccess);
                }).catch(function() {
                    this.fallbackCopyToClipboard(text);
                }.bind(this));
            } else {
                this.fallbackCopyToClipboard(text);
            }
        },

        /**
         * クリップボードにコピー（フォールバック）
         */
        fallbackCopyToClipboard: function(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();

            try {
                document.execCommand('copy');
                alert(kashiwazakiSeoHeadline.i18n.copySuccess);
            } catch (err) {
                alert('コピーに失敗しました。');
            }

            document.body.removeChild(textArea);
        },

        /**
         * ローディング表示
         */
        showLoading: function() {
            $('.kashiwazaki-seo-headline-actions .spinner').addClass('is-active');
            $('#kashiwazaki-seo-headline-analyze, #kashiwazaki-seo-headline-check-cannibalization').prop('disabled', true);
        },

        /**
         * ローディング非表示
         */
        hideLoading: function() {
            $('.kashiwazaki-seo-headline-actions .spinner').removeClass('is-active');
            $('#kashiwazaki-seo-headline-analyze').prop('disabled', false);
            if (this.currentAnalysis && this.currentAnalysis.headlines && this.currentAnalysis.headlines.length > 0) {
                $('#kashiwazaki-seo-headline-check-cannibalization').prop('disabled', false);
            }
        },

        /**
         * エラー表示
         */
        showError: function(message) {
            alert(message);
        },

        /**
         * HTMLエスケープ
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // DOM準備完了時に初期化
    $(document).ready(function() {
        KashiwazakiSeoHeadline.init();
    });

})(jQuery);
