<?php
/**
 * Plugin Name: Kashiwazaki SEO Headline Generator
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: 投稿の見出し構造を分析し、SEO最適化のための警告と提案を提供するプラグイン。階層構造バリデーション、文字数チェック、重複検出、サイト内カニバリチェック機能を搭載。
 * Version: 1.0.3
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kashiwazaki-seo-headline-generator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグイン定数
define( 'KASHIWAZAKI_SEO_HEADLINE_VERSION', '1.0.3' );
define( 'KASHIWAZAKI_SEO_HEADLINE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KASHIWAZAKI_SEO_HEADLINE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KASHIWAZAKI_SEO_HEADLINE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * メインプラグインクラス
 */
class Kashiwazaki_SEO_Headline_Generator {

    /**
     * シングルトンインスタンス
     *
     * @var Kashiwazaki_SEO_Headline_Generator
     */
    private static $instance = null;

    /**
     * 設定クラスインスタンス
     *
     * @var Kashiwazaki_SEO_Headline_Generator_Settings
     */
    public $settings;

    /**
     * メタボックスクラスインスタンス
     *
     * @var Kashiwazaki_SEO_Headline_Generator_Metabox
     */
    public $metabox;

    /**
     * アナライザークラスインスタンス
     *
     * @var Kashiwazaki_SEO_Headline_Generator_Analyzer
     */
    public $analyzer;

    /**
     * 目次クラスインスタンス
     *
     * @var Kashiwazaki_SEO_Headline_Generator_TOC
     */
    public $toc;

    /**
     * シングルトンインスタンスを取得
     *
     * @return Kashiwazaki_SEO_Headline_Generator
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * 依存ファイルを読み込み
     */
    private function load_dependencies() {
        require_once KASHIWAZAKI_SEO_HEADLINE_PLUGIN_DIR . 'includes/class-analyzer.php';
        require_once KASHIWAZAKI_SEO_HEADLINE_PLUGIN_DIR . 'includes/class-settings.php';
        require_once KASHIWAZAKI_SEO_HEADLINE_PLUGIN_DIR . 'includes/class-metabox.php';
        require_once KASHIWAZAKI_SEO_HEADLINE_PLUGIN_DIR . 'includes/class-toc.php';
    }

    /**
     * フックを初期化
     */
    private function init_hooks() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_kashiwazaki_seo_headline_analyze', array( $this, 'ajax_analyze_headlines' ) );
        add_action( 'wp_ajax_kashiwazaki_seo_headline_check_cannibalization', array( $this, 'ajax_check_cannibalization' ) );

        // アクティベーション・デアクティベーション
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        // プラグイン一覧に設定リンクを追加
        add_filter( 'plugin_action_links_' . KASHIWAZAKI_SEO_HEADLINE_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
    }

    /**
     * プラグイン一覧に設定リンクを追加
     *
     * @param array $links 既存のリンク
     * @return array 更新されたリンク
     */
    public function add_plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=kashiwazaki-seo-headline-generator' ),
            __( '設定', 'kashiwazaki-seo-headline-generator' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * プラグインを初期化
     */
    public function init() {
        $this->analyzer = new Kashiwazaki_SEO_Headline_Generator_Analyzer();
        $this->settings = new Kashiwazaki_SEO_Headline_Generator_Settings();
        $this->metabox  = new Kashiwazaki_SEO_Headline_Generator_Metabox( $this->analyzer, $this->settings );
        $this->toc      = new Kashiwazaki_SEO_Headline_Generator_TOC();

        // 見出しにIDを付与するフィルター（目次リンク用）
        // 優先度12: ショートコード処理（優先度11）の後に実行
        add_filter( 'the_content', array( $this, 'add_heading_ids' ), 12 );
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
     * 見出しにIDを付与
     *
     * @param string $content 投稿コンテンツ
     * @return string ID付与済みコンテンツ
     */
    public function add_heading_ids( $content ) {
        if ( ! is_singular() ) {
            return $content;
        }

        $options        = get_option( 'kashiwazaki_seo_headline_options', $this->get_default_options() );
        $toc_post_types = isset( $options['toc_post_types'] ) ? $options['toc_post_types'] : array( 'post', 'page' );

        if ( ! in_array( get_post_type(), $toc_post_types, true ) ) {
            return $content;
        }

        $levels = isset( $options['headline_levels'] ) ? $options['headline_levels'] : array( 'h2', 'h3', 'h4', 'h5', 'h6' );

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

                // 重複チェック
                $id = $base_id;
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

    /**
     * 管理画面用アセットを読み込み
     *
     * @param string $hook 現在のページフック
     */
    public function enqueue_admin_assets( $hook ) {
        $valid_hooks = array( 'post.php', 'post-new.php', 'toplevel_page_kashiwazaki-seo-headline-generator' );

        if ( ! in_array( $hook, $valid_hooks, true ) ) {
            return;
        }

        wp_enqueue_style(
            'kashiwazaki-seo-headline-admin',
            KASHIWAZAKI_SEO_HEADLINE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            KASHIWAZAKI_SEO_HEADLINE_VERSION
        );

        wp_enqueue_script(
            'kashiwazaki-seo-headline-admin',
            KASHIWAZAKI_SEO_HEADLINE_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            KASHIWAZAKI_SEO_HEADLINE_VERSION,
            true
        );

        $options = get_option( 'kashiwazaki_seo_headline_options', $this->get_default_options() );

        wp_localize_script(
            'kashiwazaki-seo-headline-admin',
            'kashiwazakiSeoHeadline',
            array(
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'kashiwazaki_seo_headline_nonce' ),
                'postId'    => get_the_ID(),
                'options'   => $options,
                'i18n'      => array(
                    'analyzing'         => __( '分析中...', 'kashiwazaki-seo-headline-generator' ),
                    'noHeadlines'       => __( '見出しが見つかりませんでした。', 'kashiwazaki-seo-headline-generator' ),
                    'hierarchyWarning'  => __( '階層飛びが検出されました', 'kashiwazaki-seo-headline-generator' ),
                    'lengthWarning'     => __( '文字数が推奨範囲外です', 'kashiwazaki-seo-headline-generator' ),
                    'duplicateWarning'  => __( '類似した見出しが検出されました', 'kashiwazaki-seo-headline-generator' ),
                    'cannibalWarning'   => __( 'サイト内で類似コンテンツが検出されました', 'kashiwazaki-seo-headline-generator' ),
                    'exportSuccess'     => __( 'エクスポートが完了しました', 'kashiwazaki-seo-headline-generator' ),
                    'copySuccess'       => __( 'クリップボードにコピーしました', 'kashiwazaki-seo-headline-generator' ),
                    'tooShort'          => __( '短すぎます', 'kashiwazaki-seo-headline-generator' ),
                    'tooLong'           => __( '長すぎます', 'kashiwazaki-seo-headline-generator' ),
                    'characters'        => __( '文字', 'kashiwazaki-seo-headline-generator' ),
                    'similarity'        => __( '類似度', 'kashiwazaki-seo-headline-generator' ),
                    'editPost'          => __( '編集', 'kashiwazaki-seo-headline-generator' ),
                ),
            )
        );
    }

    /**
     * デフォルトオプションを取得
     *
     * @return array
     */
    public function get_default_options() {
        return array(
            'headline_levels'           => array( 'h2', 'h3', 'h4', 'h5', 'h6' ),
            'min_length'                => 5,
            'max_length'                => 60,
            'duplicate_threshold'       => 80,
            'cannibalization_threshold' => 80,
            'post_types'                => array( 'post', 'page' ),
            'toc_post_types'            => array( 'post', 'page' ),
            'toc_auto_insert'           => true,
            'toc_insert_position'       => 'before_first_heading',
            'toc_title'                 => '目次',
            'toc_min_headings'          => 2,
            'toc_show_toggle'           => true,
            'toc_default_open'          => true,
            'toc_smooth_scroll'         => true,
            'toc_scroll_offset'         => 0,
            'toc_numbering'             => true,
        );
    }

    /**
     * AJAX: 見出しを分析
     */
    public function ajax_analyze_headlines() {
        check_ajax_referer( 'kashiwazaki_seo_headline_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません。' ) );
        }

        $content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
        $title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        $options = get_option( 'kashiwazaki_seo_headline_options', $this->get_default_options() );

        $result = $this->analyzer->analyze( $content, $title, $options );

        wp_send_json_success( $result );
    }

    /**
     * AJAX: カニバリゼーションをチェック
     */
    public function ajax_check_cannibalization() {
        check_ajax_referer( 'kashiwazaki_seo_headline_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません。' ) );
        }

        $headlines = isset( $_POST['headlines'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['headlines'] ) ) : array();
        $title     = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $post_id   = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

        $options   = get_option( 'kashiwazaki_seo_headline_options', $this->get_default_options() );
        $threshold = isset( $options['cannibalization_threshold'] ) ? intval( $options['cannibalization_threshold'] ) : 80;

        $result = $this->analyzer->check_cannibalization( $headlines, $title, $post_id, $threshold, $options );

        wp_send_json_success( $result );
    }

    /**
     * プラグインをアクティベート
     */
    public function activate() {
        $options = get_option( 'kashiwazaki_seo_headline_options' );

        if ( false === $options ) {
            update_option( 'kashiwazaki_seo_headline_options', $this->get_default_options() );
        }
    }

    /**
     * プラグインをデアクティベート
     */
    public function deactivate() {
        // クリーンアップ処理（必要に応じて）
    }
}

/**
 * プラグインインスタンスを取得
 *
 * @return Kashiwazaki_SEO_Headline_Generator
 */
function kashiwazaki_seo_headline_generator() {
    return Kashiwazaki_SEO_Headline_Generator::get_instance();
}

// プラグインを初期化
kashiwazaki_seo_headline_generator();
