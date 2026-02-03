<?php
/**
 * 目次生成クラス
 *
 * @package Kashiwazaki_SEO_Headline_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 目次を生成・表示するクラス
 */
class Kashiwazaki_SEO_Headline_Generator_TOC {

    /**
     * 設定オプション
     *
     * @var array
     */
    private $options;

    /**
     * 目次が既に挿入されたかどうか
     *
     * @var bool
     */
    private $toc_inserted = false;

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->options = $this->get_options();

        // ショートコード登録
        add_shortcode( 'kashiwazaki_toc', array( $this, 'shortcode_toc' ) );

        // 自動挿入フィルター
        // 優先度13: add_heading_ids（優先度12）の後に実行
        if ( ! empty( $this->options['toc_auto_insert'] ) ) {
            add_filter( 'the_content', array( $this, 'auto_insert_toc' ), 13 );
        }

        // フロントエンド用CSS
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
    }

    /**
     * オプションを取得
     *
     * @return array
     */
    private function get_options() {
        $defaults = array(
            'headline_levels'      => array( 'h2', 'h3', 'h4', 'h5', 'h6' ),
            'post_types'           => array( 'post', 'page' ),
            'toc_post_types'       => array( 'post', 'page' ),
            'toc_auto_insert'      => true,
            'toc_insert_position'  => 'before_first_heading',
            'toc_title'            => '目次',
            'toc_min_headings'     => 2,
            'toc_show_toggle'      => true,
            'toc_default_open'     => true,
            'toc_smooth_scroll'    => true,
            'toc_scroll_offset'    => 0,
            'toc_numbering'        => true,
            'toc_color_scheme'     => 'default',
            'toc_preview_enabled'  => true,
            'toc_preview_count'    => 3,
        );

        $options = get_option( 'kashiwazaki_seo_headline_options', array() );

        // wp_parse_args を使用してマージし、boolean値を明示的に処理
        $merged = wp_parse_args( $options, $defaults );

        // チェックボックス系のboolean設定は array_key_exists でチェックして上書き
        if ( array_key_exists( 'toc_auto_insert', $options ) ) {
            $merged['toc_auto_insert'] = ! empty( $options['toc_auto_insert'] );
        }
        if ( array_key_exists( 'toc_show_toggle', $options ) ) {
            $merged['toc_show_toggle'] = ! empty( $options['toc_show_toggle'] );
        }
        if ( array_key_exists( 'toc_default_open', $options ) ) {
            $merged['toc_default_open'] = ! empty( $options['toc_default_open'] );
        }
        if ( array_key_exists( 'toc_smooth_scroll', $options ) ) {
            $merged['toc_smooth_scroll'] = ! empty( $options['toc_smooth_scroll'] );
        }
        if ( array_key_exists( 'toc_numbering', $options ) ) {
            $merged['toc_numbering'] = ! empty( $options['toc_numbering'] );
        }

        return $merged;
    }

    /**
     * フロントエンド用CSSを読み込み
     */
    public function enqueue_frontend_styles() {
        if ( ! is_singular() ) {
            return;
        }

        $toc_post_types = isset( $this->options['toc_post_types'] ) ? $this->options['toc_post_types'] : array( 'post', 'page' );

        if ( ! in_array( get_post_type(), $toc_post_types, true ) ) {
            return;
        }

        wp_enqueue_style(
            'kashiwazaki-seo-headline-toc',
            KASHIWAZAKI_SEO_HEADLINE_PLUGIN_URL . 'assets/css/toc.css',
            array(),
            KASHIWAZAKI_SEO_HEADLINE_VERSION
        );

        if ( ! empty( $this->options['toc_smooth_scroll'] ) ) {
            $scroll_offset = isset( $this->options['toc_scroll_offset'] ) ? intval( $this->options['toc_scroll_offset'] ) : 0;
            // wp_footerでスクリプトを出力（キャプチャフェーズで処理し、テーマのハンドラーより先に実行）
            add_action( 'wp_footer', function() use ( $scroll_offset ) {
                ?>
                <script>
                (function(){
                    var manualOffset = <?php echo esc_js( $scroll_offset ); ?>;

                    // 固定ヘッダーを自動検知する関数
                    function detectFixedHeaderHeight() {
                        var headers = document.querySelectorAll('header, .header, .site-header, [role="banner"], .navbar, .nav-header, #masthead');
                        var maxHeight = 0;
                        headers.forEach(function(el) {
                            var style = window.getComputedStyle(el);
                            var position = style.position;
                            if (position === 'fixed' || position === 'sticky') {
                                var height = el.offsetHeight;
                                if (height > maxHeight) maxHeight = height;
                            }
                        });
                        return maxHeight;
                    }

                    function getScrollOffset() {
                        // 手動設定があればそれを優先
                        if (manualOffset > 0) return manualOffset;
                        // 自動検知（少し余白を追加）
                        var detected = detectFixedHeaderHeight();
                        return detected > 0 ? detected + 10 : 0;
                    }

                    document.addEventListener('click', function(e){
                        var link = e.target.closest('.kashiwazaki-toc a[href^="#"]');
                        if(!link) return;

                        var targetId = link.getAttribute('href');
                        try {
                            targetId = decodeURIComponent(targetId);
                        } catch(err) {}
                        var target = document.getElementById(targetId.substring(1));
                        if(!target) return;

                        e.preventDefault();
                        e.stopImmediatePropagation();

                        var scrollOffset = getScrollOffset();
                        var targetTop = target.getBoundingClientRect().top + window.pageYOffset - scrollOffset;
                        window.scrollTo({
                            top: targetTop,
                            behavior: 'smooth'
                        });
                        history.pushState(null, null, targetId);
                    }, true);
                })();
                </script>
                <?php
            }, 99 );
        }
    }

    /**
     * ショートコード: 目次を表示
     *
     * @param array $atts ショートコード属性
     * @return string 目次HTML
     */
    public function shortcode_toc( $atts ) {
        $atts = shortcode_atts(
            array(
                'title' => $this->options['toc_title'],
            ),
            $atts,
            'kashiwazaki_toc'
        );

        // ショートコードで挿入された場合は自動挿入をスキップ
        $this->toc_inserted = true;

        global $post;
        if ( ! $post ) {
            return '';
        }

        return $this->generate_toc( $post->post_content, $atts['title'] );
    }

    /**
     * 自動挿入: コンテンツに目次を追加
     *
     * @param string $content 投稿コンテンツ
     * @return string 目次付きコンテンツ
     */
    public function auto_insert_toc( $content ) {
        // 既にショートコードで挿入されている場合はスキップ
        if ( $this->toc_inserted ) {
            return $content;
        }

        // シングルページのみ
        if ( ! is_singular() ) {
            return $content;
        }

        // 対象投稿タイプをチェック
        $toc_post_types = isset( $this->options['toc_post_types'] ) ? $this->options['toc_post_types'] : array( 'post', 'page' );
        if ( ! in_array( get_post_type(), $toc_post_types, true ) ) {
            return $content;
        }

        // フィードでは表示しない
        if ( is_feed() ) {
            return $content;
        }

        $toc = $this->generate_toc( $content, $this->options['toc_title'] );

        if ( empty( $toc ) ) {
            return $content;
        }

        $position = isset( $this->options['toc_insert_position'] ) ? $this->options['toc_insert_position'] : 'before_first_heading';

        switch ( $position ) {
            case 'before_first_heading':
                // 最初の見出しの前に挿入
                $pattern = '/<h[2-6][^>]*>/i';
                if ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
                    $pos     = $matches[0][1];
                    $content = substr( $content, 0, $pos ) . $toc . substr( $content, $pos );
                } else {
                    $content = $toc . $content;
                }
                break;

            case 'after_first_paragraph':
                // 最初の段落の後に挿入
                $pattern = '/<\/p>/i';
                if ( preg_match( $pattern, $content, $matches, PREG_OFFSET_CAPTURE ) ) {
                    $pos     = $matches[0][1] + strlen( $matches[0][0] );
                    $content = substr( $content, 0, $pos ) . $toc . substr( $content, $pos );
                } else {
                    $content = $toc . $content;
                }
                break;

            case 'top':
            default:
                $content = $toc . $content;
                break;
        }

        return $content;
    }

    /**
     * 目次HTMLを生成
     *
     * @param string $content コンテンツ
     * @param string $title   目次タイトル
     * @return string 目次HTML
     */
    public function generate_toc( $content, $title = '' ) {
        $headings = $this->extract_headings( $content );

        // 最小見出し数をチェック
        $min_headings = isset( $this->options['toc_min_headings'] ) ? intval( $this->options['toc_min_headings'] ) : 2;
        if ( count( $headings ) < $min_headings ) {
            return '';
        }

        if ( empty( $title ) ) {
            $title = isset( $this->options['toc_title'] ) ? $this->options['toc_title'] : '目次';
        }

        $show_toggle     = ! empty( $this->options['toc_show_toggle'] );
        $default_open    = ! empty( $this->options['toc_default_open'] );
        $numbering       = ! empty( $this->options['toc_numbering'] );
        $color_scheme    = isset( $this->options['toc_color_scheme'] ) ? $this->options['toc_color_scheme'] : 'default';
        $preview_enabled = ! empty( $this->options['toc_preview_enabled'] );
        $preview_count   = isset( $this->options['toc_preview_count'] ) ? intval( $this->options['toc_preview_count'] ) : 3;

        // 開閉ボタンがない場合は常に開いた状態にする
        if ( ! $show_toggle ) {
            $default_open = true;
        }

        $open_class    = $default_open ? 'is-open' : '';
        $scheme_class  = 'scheme-' . sanitize_html_class( $color_scheme );
        $preview_class = $preview_enabled ? 'has-preview' : 'no-preview';

        $html = '<div class="kashiwazaki-toc ' . esc_attr( $open_class ) . ' ' . esc_attr( $scheme_class ) . ' ' . esc_attr( $preview_class ) . '" data-preview-count="' . esc_attr( $preview_count ) . '">';
        $html .= '<div class="kashiwazaki-toc-header">';
        $html .= '<span class="kashiwazaki-toc-title">' . esc_html( $title ) . '</span>';
        $html .= '</div>';
        $html .= '<nav class="kashiwazaki-toc-content">';
        $html .= $this->build_toc_list( $headings, $numbering );
        $html .= '</nav>';

        if ( $show_toggle ) {
            $open_text = $preview_enabled ? 'もっと見る' : '開く';
            $html .= '<div class="kashiwazaki-toc-footer">';
            $html .= '<button type="button" class="kashiwazaki-toc-toggle" aria-expanded="' . ( $default_open ? 'true' : 'false' ) . '" aria-label="' . ( $default_open ? '目次を閉じる' : '目次を開く' ) . '">';
            $html .= '<span class="toggle-text-close">閉じる</span>';
            $html .= '<span class="toggle-text-open">' . esc_html( $open_text ) . '</span>';
            $html .= '</button>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // トグル用インラインスクリプト
        if ( $show_toggle ) {
            $html .= '<script>
                document.addEventListener("DOMContentLoaded", function(){
                    var toc = document.querySelector(".kashiwazaki-toc");
                    var tocToggle = document.querySelector(".kashiwazaki-toc-toggle");
                    var tocContent = document.querySelector(".kashiwazaki-toc-content");

                    if(toc && tocContent){
                        // チラ見せ件数に基づいてアイテムを表示/非表示
                        var previewCount = parseInt(toc.getAttribute("data-preview-count")) || 3;
                        var hasPreview = toc.classList.contains("has-preview");
                        // すべてのli要素を取得（ネストされたものも含む）
                        var allItems = tocContent.querySelectorAll(".kashiwazaki-toc-item");

                        function updatePreview() {
                            var isOpen = toc.classList.contains("is-open");
                            allItems.forEach(function(item, index) {
                                if(isOpen || !hasPreview) {
                                    item.style.display = "";
                                } else {
                                    item.style.display = index < previewCount ? "" : "none";
                                }
                            });
                        }

                        // 初期表示
                        updatePreview();

                        if(tocToggle){
                            tocToggle.addEventListener("click", function(){
                                var isOpen = toc.classList.contains("is-open");
                                if(isOpen){
                                    toc.classList.remove("is-open");
                                    this.setAttribute("aria-expanded", "false");
                                    this.setAttribute("aria-label", "目次を開く");
                                } else {
                                    toc.classList.add("is-open");
                                    this.setAttribute("aria-expanded", "true");
                                    this.setAttribute("aria-label", "目次を閉じる");
                                }
                                updatePreview();
                            });
                        }
                    }
                });
            </script>';
        }

        return $html;
    }

    /**
     * 見出しテキストからスラッグを生成
     *
     * @param string $text 見出しテキスト
     * @return string スラッグ
     */
    private function generate_heading_slug( $text ) {
        // HTMLタグを除去
        $text = wp_strip_all_tags( $text );
        // HTMLエンティティをデコード
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
        // 前後の空白を除去
        $text = trim( $text );
        // 空白をハイフンに変換
        $text = preg_replace( '/\s+/', '-', $text );
        // 英数字、日本語、ハイフン以外を除去
        $text = preg_replace( '/[^\p{L}\p{N}\-]/u', '', $text );
        // 連続するハイフンを1つに
        $text = preg_replace( '/-+/', '-', $text );
        // 前後のハイフンを除去
        $text = trim( $text, '-' );
        // 小文字に変換（英字のみ）
        $text = mb_strtolower( $text, 'UTF-8' );
        // 空の場合はフォールバック
        if ( empty( $text ) ) {
            $text = 'heading';
        }
        return $text;
    }

    /**
     * コンテンツから見出しを抽出
     *
     * @param string $content コンテンツ
     * @return array 見出し配列
     */
    private function extract_headings( $content ) {
        $headings = array();
        $used_ids = array();
        $levels   = isset( $this->options['headline_levels'] ) ? $this->options['headline_levels'] : array( 'h2', 'h3', 'h4', 'h5', 'h6' );

        // 対象見出しタグのパターンを作成
        $level_nums = array();
        foreach ( $levels as $level ) {
            $level_nums[] = substr( $level, 1 );
        }
        $level_pattern = implode( '', $level_nums );

        // 見出しを抽出
        $pattern = '/<h([' . $level_pattern . '])([^>]*)>(.*?)<\/h\1>/is';

        if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
            $counter = 0;
            foreach ( $matches as $match ) {
                $level = intval( $match[1] );
                $attrs = $match[2];
                $text  = wp_strip_all_tags( $match[3] );

                // 既存のIDを取得、なければテキストからスラッグを生成
                $id = '';
                if ( preg_match( '/id=["\']([^"\']+)["\']/', $attrs, $id_match ) ) {
                    $id = $id_match[1];
                } else {
                    // 見出しテキストからスラッグを生成
                    $base_id = $this->generate_heading_slug( $match[3] );
                    $id      = $base_id;
                    $num     = 2;
                    while ( isset( $used_ids[ $id ] ) ) {
                        $id = $base_id . '-' . $num;
                        $num++;
                    }
                }
                $used_ids[ $id ] = true;

                $headings[] = array(
                    'level' => $level,
                    'text'  => $text,
                    'id'    => $id,
                    'index' => $counter,
                );

                $counter++;
            }
        }

        return $headings;
    }

    /**
     * 目次リストHTMLを構築
     *
     * @param array $headings  見出し配列
     * @param bool  $numbering 番号付けするか
     * @return string リストHTML
     */
    private function build_toc_list( $headings, $numbering = true ) {
        if ( empty( $headings ) ) {
            return '';
        }

        // 番号付けの設定に応じてリストタグを変更
        $list_tag    = $numbering ? 'ol' : 'ul';
        $sublist_class = $numbering ? 'kashiwazaki-toc-sublist' : 'kashiwazaki-toc-sublist no-numbering';

        $html         = '<' . $list_tag . ' class="kashiwazaki-toc-list' . ( $numbering ? '' : ' no-numbering' ) . '">';
        $current_level = $headings[0]['level'];
        $counters     = array( 0, 0, 0, 0, 0, 0 );

        foreach ( $headings as $heading ) {
            $level = $heading['level'];

            // レベルが深くなった場合
            while ( $level > $current_level ) {
                $html .= '<' . $list_tag . ' class="' . esc_attr( $sublist_class ) . '">';
                $current_level++;
            }

            // レベルが浅くなった場合
            while ( $level < $current_level ) {
                $html .= '</li></' . $list_tag . '>';
                $counters[ $current_level - 1 ] = 0;
                $current_level--;
            }

            // カウンターを更新
            $counters[ $level - 1 ]++;

            // 番号を生成（番号付けが有効な場合のみ）
            $number = '';
            if ( $numbering ) {
                $number_parts = array();
                for ( $i = $headings[0]['level'] - 1; $i < $level; $i++ ) {
                    $number_parts[] = $counters[ $i ];
                }
                $number = '<span class="toc-number">' . implode( '.', $number_parts ) . '</span> ';
            }

            $html .= '<li class="kashiwazaki-toc-item level-' . esc_attr( $level ) . '">';
            $html .= '<a href="#' . esc_attr( $heading['id'] ) . '">' . $number . esc_html( $heading['text'] ) . '</a>';
        }

        // 残りのタグを閉じる
        while ( $current_level >= $headings[0]['level'] ) {
            $html .= '</li></' . $list_tag . '>';
            $current_level--;
        }

        return $html;
    }

    /**
     * コンテンツの見出しにIDを付与
     *
     * @param string $content コンテンツ
     * @return string ID付与済みコンテンツ
     */
    public function add_heading_ids( $content ) {
        $levels = isset( $this->options['headline_levels'] ) ? $this->options['headline_levels'] : array( 'h2', 'h3', 'h4', 'h5', 'h6' );

        $level_nums = array();
        foreach ( $levels as $level ) {
            $level_nums[] = substr( $level, 1 );
        }
        $level_pattern = implode( '', $level_nums );

        $used_ids = array();
        $pattern  = '/<h([' . $level_pattern . '])([^>]*)>(.*?)<\/h\1>/is';

        $self    = $this;
        $content = preg_replace_callback(
            $pattern,
            function ( $matches ) use ( &$used_ids, $self ) {
                $level = $matches[1];
                $attrs = $matches[2];
                $text  = $matches[3];

                // 既にIDがある場合はそのまま（ただし使用済みリストに追加）
                if ( preg_match( '/id=["\']([^"\']+)["\']/', $attrs, $id_match ) ) {
                    $used_ids[ $id_match[1] ] = true;
                    return $matches[0];
                }

                // 見出しテキストからスラッグを生成
                $base_id = $self->generate_heading_slug( $text );
                $id      = $base_id;
                $counter = 2;
                while ( isset( $used_ids[ $id ] ) ) {
                    $id = $base_id . '-' . $counter;
                    $counter++;
                }
                $used_ids[ $id ] = true;

                return '<h' . $level . ' id="' . esc_attr( $id ) . '"' . $attrs . '>' . $text . '</h' . $level . '>';
            },
            $content
        );

        return $content;
    }
}
