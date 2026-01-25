<?php
/**
 * 設定画面クラス
 *
 * @package Kashiwazaki_SEO_Headline_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 設定画面を管理するクラス
 */
class Kashiwazaki_SEO_Headline_Generator_Settings {

    /**
     * オプション名
     *
     * @var string
     */
    private $option_name = 'kashiwazaki_seo_headline_options';

    /**
     * コンストラクタ
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * 管理メニューを追加
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Kashiwazaki SEO Headline Generator', 'kashiwazaki-seo-headline-generator' ),
            __( 'Kashiwazaki SEO Headline Generator', 'kashiwazaki-seo-headline-generator' ),
            'manage_options',
            'kashiwazaki-seo-headline-generator',
            array( $this, 'render_settings_page' ),
            'dashicons-editor-ul',
            81
        );
    }

    /**
     * 設定を登録
     */
    public function register_settings() {
        register_setting(
            'kashiwazaki_seo_headline_settings',
            $this->option_name,
            array( $this, 'sanitize_options' )
        );
    }

    /**
     * オプションをサニタイズ
     *
     * @param array $input 入力値
     * @return array サニタイズ済み値
     */
    public function sanitize_options( $input ) {
        // 既存の設定を取得してベースにする
        $existing  = get_option( $this->option_name, $this->get_default_options() );
        $sanitized = is_array( $existing ) ? $existing : $this->get_default_options();

        // どのタブから送信されたかを判定
        $active_tab = isset( $input['active_tab'] ) ? $input['active_tab'] : 'analysis';

        // 見出し分析タブの設定
        if ( 'analysis' === $active_tab ) {
            // 見出しレベル
            $valid_levels = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
            $sanitized['headline_levels'] = array();
            if ( isset( $input['headline_levels'] ) && is_array( $input['headline_levels'] ) ) {
                foreach ( $input['headline_levels'] as $level ) {
                    if ( in_array( $level, $valid_levels, true ) ) {
                        $sanitized['headline_levels'][] = $level;
                    }
                }
            }
            if ( empty( $sanitized['headline_levels'] ) ) {
                $sanitized['headline_levels'] = array( 'h2', 'h3', 'h4', 'h5', 'h6' );
            }

            // 最小文字数
            $sanitized['min_length'] = isset( $input['min_length'] ) ? absint( $input['min_length'] ) : 5;
            if ( $sanitized['min_length'] < 1 ) {
                $sanitized['min_length'] = 1;
            }

            // 最大文字数
            $sanitized['max_length'] = isset( $input['max_length'] ) ? absint( $input['max_length'] ) : 60;
            if ( $sanitized['max_length'] < $sanitized['min_length'] ) {
                $sanitized['max_length'] = $sanitized['min_length'] + 10;
            }

            // 重複検出閾値
            $sanitized['duplicate_threshold'] = isset( $input['duplicate_threshold'] ) ? absint( $input['duplicate_threshold'] ) : 80;
            if ( $sanitized['duplicate_threshold'] < 1 ) {
                $sanitized['duplicate_threshold'] = 1;
            }
            if ( $sanitized['duplicate_threshold'] > 100 ) {
                $sanitized['duplicate_threshold'] = 100;
            }

            // カニバリゼーション閾値
            $sanitized['cannibalization_threshold'] = isset( $input['cannibalization_threshold'] ) ? absint( $input['cannibalization_threshold'] ) : 80;
            if ( $sanitized['cannibalization_threshold'] < 1 ) {
                $sanitized['cannibalization_threshold'] = 1;
            }
            if ( $sanitized['cannibalization_threshold'] > 100 ) {
                $sanitized['cannibalization_threshold'] = 100;
            }

            // 投稿タイプ（カニバリチェック用）
            $sanitized['post_types'] = array();
            if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
                foreach ( $input['post_types'] as $post_type ) {
                    $sanitized['post_types'][] = sanitize_key( $post_type );
                }
            }
            if ( empty( $sanitized['post_types'] ) ) {
                $sanitized['post_types'] = array( 'post', 'page' );
            }
        }

        // 目次タブの設定
        if ( 'toc' === $active_tab ) {
            // 目次対象投稿タイプ
            $sanitized['toc_post_types'] = array();
            if ( isset( $input['toc_post_types'] ) && is_array( $input['toc_post_types'] ) ) {
                foreach ( $input['toc_post_types'] as $post_type ) {
                    $sanitized['toc_post_types'][] = sanitize_key( $post_type );
                }
            }
            if ( empty( $sanitized['toc_post_types'] ) ) {
                $sanitized['toc_post_types'] = array( 'post', 'page' );
            }

            // 目次設定
            $sanitized['toc_auto_insert']     = ! empty( $input['toc_auto_insert'] );
            $sanitized['toc_insert_position'] = isset( $input['toc_insert_position'] ) ? sanitize_key( $input['toc_insert_position'] ) : 'before_first_heading';
            $sanitized['toc_title']           = isset( $input['toc_title'] ) ? sanitize_text_field( $input['toc_title'] ) : '目次';
            $sanitized['toc_min_headings']    = isset( $input['toc_min_headings'] ) ? absint( $input['toc_min_headings'] ) : 2;
            $sanitized['toc_show_toggle']     = ! empty( $input['toc_show_toggle'] );
            $sanitized['toc_default_open']    = ! empty( $input['toc_default_open'] );
            $sanitized['toc_smooth_scroll']   = ! empty( $input['toc_smooth_scroll'] );
            $sanitized['toc_scroll_offset']   = isset( $input['toc_scroll_offset'] ) ? absint( $input['toc_scroll_offset'] ) : 0;
            $sanitized['toc_numbering']       = ! empty( $input['toc_numbering'] );

            if ( $sanitized['toc_min_headings'] < 1 ) {
                $sanitized['toc_min_headings'] = 1;
            }
        }

        // デザインタブの設定
        if ( 'design' === $active_tab ) {
            $valid_schemes = array( 'default', 'blue', 'green', 'orange', 'purple', 'dark' );
            $sanitized['toc_color_scheme'] = isset( $input['toc_color_scheme'] ) && in_array( $input['toc_color_scheme'], $valid_schemes, true )
                ? $input['toc_color_scheme']
                : 'default';
        }

        return $sanitized;
    }

    /**
     * デフォルトオプションを取得
     *
     * @return array
     */
    private function get_default_options() {
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
            'toc_color_scheme'          => 'default',
        );
    }

    /**
     * オプションを取得
     *
     * @return array
     */
    public function get_options() {
        $options  = get_option( $this->option_name );
        $defaults = $this->get_default_options();

        if ( false === $options ) {
            return $defaults;
        }

        return wp_parse_args( $options, $defaults );
    }

    /**
     * 設定ページをレンダリング
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options = $this->get_options();
        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'analysis';

        // 設定保存時のメッセージ
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error(
                'kashiwazaki_seo_headline_messages',
                'kashiwazaki_seo_headline_message',
                __( '設定を保存しました。', 'kashiwazaki-seo-headline-generator' ),
                'updated'
            );
        }

        ?>
        <div class="wrap kashiwazaki-seo-settings-wrap">
            <h1><?php esc_html_e( 'Kashiwazaki SEO Headline Generator', 'kashiwazaki-seo-headline-generator' ); ?></h1>

            <?php settings_errors( 'kashiwazaki_seo_headline_messages' ); ?>

            <nav class="kashiwazaki-tabs">
                <a href="?page=kashiwazaki-seo-headline-generator&tab=analysis"
                   class="kashiwazaki-tab <?php echo $active_tab === 'analysis' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-search"></span>
                    見出し分析
                </a>
                <a href="?page=kashiwazaki-seo-headline-generator&tab=toc"
                   class="kashiwazaki-tab <?php echo $active_tab === 'toc' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span>
                    目次
                </a>
                <a href="?page=kashiwazaki-seo-headline-generator&tab=design"
                   class="kashiwazaki-tab <?php echo $active_tab === 'design' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-art"></span>
                    デザイン
                </a>
                <a href="?page=kashiwazaki-seo-headline-generator&tab=help"
                   class="kashiwazaki-tab <?php echo $active_tab === 'help' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                    使い方
                </a>
            </nav>

            <form action="options.php" method="post" class="kashiwazaki-settings-form">
                <?php settings_fields( 'kashiwazaki_seo_headline_settings' ); ?>

                <?php if ( $active_tab === 'analysis' ) : ?>
                    <?php $this->render_tab_analysis( $options ); ?>
                <?php elseif ( $active_tab === 'toc' ) : ?>
                    <?php $this->render_tab_toc( $options ); ?>
                <?php elseif ( $active_tab === 'design' ) : ?>
                    <?php $this->render_tab_design( $options ); ?>
                <?php elseif ( $active_tab === 'help' ) : ?>
                    <?php $this->render_tab_help(); ?>
                <?php endif; ?>

                <?php if ( $active_tab !== 'help' ) : ?>
                    <?php submit_button( __( '設定を保存', 'kashiwazaki-seo-headline-generator' ) ); ?>
                <?php endif; ?>
            </form>
        </div>

        <style>
        .kashiwazaki-seo-settings-wrap {
            max-width: 900px;
        }

        /* タブナビゲーション */
        .kashiwazaki-tabs {
            display: flex;
            gap: 0;
            margin: 20px 0 0;
            border-bottom: 1px solid #c3c4c7;
        }

        .kashiwazaki-tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 12px 20px;
            text-decoration: none;
            color: #50575e;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid transparent;
            border-bottom: none;
            margin-bottom: -1px;
            background: transparent;
            transition: all 0.2s;
        }

        .kashiwazaki-tab:hover {
            color: #2271b1;
            background: #f6f7f7;
        }

        .kashiwazaki-tab.active {
            color: #1d2327;
            background: #fff;
            border-color: #c3c4c7;
            border-bottom-color: #fff;
        }

        .kashiwazaki-tab .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        /* 設定フォーム */
        .kashiwazaki-settings-form {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-top: none;
            padding: 20px;
        }

        /* カード */
        .kashiwazaki-card {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .kashiwazaki-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px 20px;
            background: #f6f7f7;
            border-bottom: 1px solid #dcdcde;
            border-radius: 4px 4px 0 0;
        }

        .kashiwazaki-card-header .dashicons {
            color: #2271b1;
            font-size: 20px;
            width: 20px;
            height: 20px;
        }

        .kashiwazaki-card-header h3 {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
            color: #1d2327;
        }

        .kashiwazaki-card-body {
            padding: 20px;
        }

        /* 設定グループ */
        .kashiwazaki-setting-group {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f1;
        }

        .kashiwazaki-setting-group:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .kashiwazaki-setting-label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1d2327;
        }

        .kashiwazaki-setting-description {
            font-size: 13px;
            color: #646970;
            margin-top: 6px;
        }

        /* チェックボックスグループ */
        .kashiwazaki-checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .kashiwazaki-checkbox-group label {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .kashiwazaki-checkbox-group.vertical {
            flex-direction: column;
            gap: 8px;
        }

        /* 投稿タイプリスト */
        .kashiwazaki-post-type-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #dcdcde;
            border-radius: 4px;
        }

        .kashiwazaki-post-type-list label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            background: #fff;
            border-radius: 3px;
            cursor: pointer;
        }

        .kashiwazaki-post-type-list label:hover {
            background: #f0f0f1;
        }

        .kashiwazaki-bulk-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        /* インプットフィールド */
        .kashiwazaki-inline-input {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .kashiwazaki-inline-input input[type="number"] {
            width: 80px;
        }

        /* 切り替えスイッチ */
        .kashiwazaki-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .kashiwazaki-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .kashiwazaki-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .kashiwazaki-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #c3c4c7;
            transition: .3s;
            border-radius: 24px;
        }

        .kashiwazaki-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .kashiwazaki-slider {
            background-color: #2271b1;
        }

        input:checked + .kashiwazaki-slider:before {
            transform: translateX(20px);
        }

        /* セレクトボックス */
        .kashiwazaki-select {
            min-width: 200px;
        }

        /* ヘルプタブ */
        .kashiwazaki-help-section {
            margin-bottom: 30px;
        }

        .kashiwazaki-help-section h2 {
            font-size: 16px;
            margin: 0 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2271b1;
        }

        .kashiwazaki-help-section ol,
        .kashiwazaki-help-section ul {
            margin-left: 20px;
            line-height: 1.8;
        }

        .kashiwazaki-feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
        }

        .kashiwazaki-feature-item {
            display: flex;
            gap: 12px;
            padding: 15px;
            background: #f6f7f7;
            border-radius: 4px;
        }

        .kashiwazaki-feature-item .dashicons {
            color: #2271b1;
            font-size: 24px;
            width: 24px;
            height: 24px;
            flex-shrink: 0;
        }

        .kashiwazaki-feature-item h4 {
            margin: 0 0 5px;
            font-size: 14px;
        }

        .kashiwazaki-feature-item p {
            margin: 0;
            font-size: 13px;
            color: #646970;
        }

        .kashiwazaki-shortcode-box {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background: #1d2327;
            color: #fff;
            font-family: monospace;
            border-radius: 4px;
            margin: 10px 0;
        }

        .kashiwazaki-shortcode-box code {
            background: transparent;
            color: #72aee6;
            font-size: 14px;
        }

        /* レスポンシブ */
        @media screen and (max-width: 782px) {
            .kashiwazaki-tabs {
                flex-wrap: wrap;
            }

            .kashiwazaki-tab {
                flex: 1;
                justify-content: center;
                padding: 10px 15px;
            }

            .kashiwazaki-checkbox-group {
                flex-direction: column;
            }

            .kashiwazaki-feature-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }

    /**
     * 見出し分析タブをレンダリング
     *
     * @param array $options オプション
     */
    private function render_tab_analysis( $options ) {
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <input type="hidden" name="<?php echo esc_attr( $this->option_name ); ?>[active_tab]" value="analysis">

        <!-- 対象投稿タイプ -->
        <div class="kashiwazaki-card">
            <div class="kashiwazaki-card-header">
                <span class="dashicons dashicons-admin-post"></span>
                <h3>対象投稿タイプ</h3>
            </div>
            <div class="kashiwazaki-card-body">
                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">分析・カニバリチェック対象</span>
                    <div class="kashiwazaki-bulk-actions">
                        <button type="button" class="button button-small" id="kashiwazaki-post-types-select-all">全選択</button>
                        <button type="button" class="button button-small" id="kashiwazaki-post-types-deselect-all">全解除</button>
                    </div>
                    <div class="kashiwazaki-post-type-list">
                        <?php foreach ( $post_types as $post_type ) : ?>
                            <?php if ( 'attachment' === $post_type->name ) continue; ?>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( $this->option_name ); ?>[post_types][]"
                                       value="<?php echo esc_attr( $post_type->name ); ?>"
                                       class="kashiwazaki-post-type-checkbox"
                                       <?php checked( in_array( $post_type->name, $options['post_types'], true ) ); ?>>
                                <?php echo esc_html( $post_type->labels->name ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="kashiwazaki-setting-description">編集画面のメタボックスに見出し分析機能が表示され、カニバリチェック時の比較対象となります。</p>
                </div>
            </div>
        </div>

        <!-- 見出し抽出設定 -->
        <div class="kashiwazaki-card">
            <div class="kashiwazaki-card-header">
                <span class="dashicons dashicons-heading"></span>
                <h3>見出し抽出設定</h3>
            </div>
            <div class="kashiwazaki-card-body">
                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">抽出対象の見出しレベル</span>
                    <div class="kashiwazaki-checkbox-group">
                        <?php
                        $levels = array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6' );
                        foreach ( $levels as $value => $label ) :
                        ?>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( $this->option_name ); ?>[headline_levels][]"
                                       value="<?php echo esc_attr( $value ); ?>"
                                       <?php checked( in_array( $value, $options['headline_levels'], true ) ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="kashiwazaki-setting-description">分析対象とする見出しタグを選択します。目次にも同じ設定が適用されます。</p>
                </div>
            </div>
        </div>

        <!-- 文字数チェック -->
        <div class="kashiwazaki-card">
            <div class="kashiwazaki-card-header">
                <span class="dashicons dashicons-editor-textcolor"></span>
                <h3>文字数チェック</h3>
            </div>
            <div class="kashiwazaki-card-body">
                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">推奨文字数の範囲</span>
                    <div class="kashiwazaki-inline-input">
                        <input type="number"
                               name="<?php echo esc_attr( $this->option_name ); ?>[min_length]"
                               value="<?php echo esc_attr( $options['min_length'] ); ?>"
                               min="1" max="100" class="small-text">
                        <span>〜</span>
                        <input type="number"
                               name="<?php echo esc_attr( $this->option_name ); ?>[max_length]"
                               value="<?php echo esc_attr( $options['max_length'] ); ?>"
                               min="1" max="500" class="small-text">
                        <span>文字</span>
                    </div>
                    <p class="kashiwazaki-setting-description">この範囲外の見出しには警告が表示されます。</p>
                </div>
            </div>
        </div>

        <!-- 類似度チェック -->
        <div class="kashiwazaki-card">
            <div class="kashiwazaki-card-header">
                <span class="dashicons dashicons-randomize"></span>
                <h3>類似度チェック</h3>
            </div>
            <div class="kashiwazaki-card-body">
                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">記事内の見出し重複検出</span>
                    <div class="kashiwazaki-inline-input">
                        <input type="number"
                               name="<?php echo esc_attr( $this->option_name ); ?>[duplicate_threshold]"
                               value="<?php echo esc_attr( $options['duplicate_threshold'] ); ?>"
                               min="1" max="100" class="small-text">
                        <span>%以上で警告</span>
                    </div>
                    <p class="kashiwazaki-setting-description">同一記事内で類似した見出しを検出します。</p>
                </div>

                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">サイト内カニバリチェック</span>
                    <div class="kashiwazaki-inline-input">
                        <input type="number"
                               name="<?php echo esc_attr( $this->option_name ); ?>[cannibalization_threshold]"
                               value="<?php echo esc_attr( $options['cannibalization_threshold'] ); ?>"
                               min="1" max="100" class="small-text">
                        <span>%以上で警告</span>
                    </div>
                    <p class="kashiwazaki-setting-description">他の公開済み記事と類似したコンテンツがないかチェックします。</p>
                </div>
            </div>
        </div>

        <?php
    }

    /**
     * 目次タブをレンダリング
     *
     * @param array $options オプション
     */
    private function render_tab_toc( $options ) {
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <input type="hidden" name="<?php echo esc_attr( $this->option_name ); ?>[active_tab]" value="toc">

        <!-- 基本設定 -->
        <div class="kashiwazaki-card">
            <div class="kashiwazaki-card-header">
                <span class="dashicons dashicons-admin-settings"></span>
                <h3>基本設定</h3>
            </div>
            <div class="kashiwazaki-card-body">
                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">対象投稿タイプ</span>
                    <div class="kashiwazaki-bulk-actions">
                        <button type="button" class="button button-small" id="kashiwazaki-toc-post-types-select-all">全選択</button>
                        <button type="button" class="button button-small" id="kashiwazaki-toc-post-types-deselect-all">全解除</button>
                    </div>
                    <div class="kashiwazaki-post-type-list">
                        <?php foreach ( $post_types as $post_type ) : ?>
                            <?php if ( 'attachment' === $post_type->name ) continue; ?>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( $this->option_name ); ?>[toc_post_types][]"
                                       value="<?php echo esc_attr( $post_type->name ); ?>"
                                       class="kashiwazaki-toc-post-type-checkbox"
                                       <?php checked( in_array( $post_type->name, $options['toc_post_types'], true ) ); ?>>
                                <?php echo esc_html( $post_type->labels->name ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="kashiwazaki-setting-description">目次を表示する投稿タイプを選択します。</p>
                </div>

                <div class="kashiwazaki-setting-group">
                    <div class="kashiwazaki-toggle">
                        <label class="kashiwazaki-switch">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( $this->option_name ); ?>[toc_auto_insert]"
                                   value="1"
                                   <?php checked( ! empty( $options['toc_auto_insert'] ) ); ?>>
                            <span class="kashiwazaki-slider"></span>
                        </label>
                        <span class="kashiwazaki-setting-label" style="margin-bottom: 0;">自動挿入を有効にする</span>
                    </div>
                    <p class="kashiwazaki-setting-description">オフにするとショートコード <code>[kashiwazaki_toc]</code> でのみ目次を表示します。</p>
                </div>

                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">挿入位置</span>
                    <select name="<?php echo esc_attr( $this->option_name ); ?>[toc_insert_position]" class="kashiwazaki-select">
                        <?php
                        $positions = array(
                            'before_first_heading'  => '最初の見出しの前',
                            'after_first_paragraph' => '最初の段落の後',
                            'top'                   => 'コンテンツの先頭',
                        );
                        foreach ( $positions as $value => $label ) :
                        ?>
                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $options['toc_insert_position'], $value ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="kashiwazaki-setting-description">自動挿入時の目次の表示位置を選択します。</p>
                </div>

                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">最小見出し数</span>
                    <div class="kashiwazaki-inline-input">
                        <input type="number"
                               name="<?php echo esc_attr( $this->option_name ); ?>[toc_min_headings]"
                               value="<?php echo esc_attr( $options['toc_min_headings'] ); ?>"
                               min="1" max="20" class="small-text">
                        <span>個以上の見出しがある場合に表示</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 表示設定 -->
        <div class="kashiwazaki-card">
            <div class="kashiwazaki-card-header">
                <span class="dashicons dashicons-visibility"></span>
                <h3>表示設定</h3>
            </div>
            <div class="kashiwazaki-card-body">
                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">目次タイトル</span>
                    <input type="text"
                           name="<?php echo esc_attr( $this->option_name ); ?>[toc_title]"
                           value="<?php echo esc_attr( $options['toc_title'] ); ?>"
                           class="regular-text">
                </div>

                <div class="kashiwazaki-setting-group">
                    <div class="kashiwazaki-toggle">
                        <label class="kashiwazaki-switch">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( $this->option_name ); ?>[toc_numbering]"
                                   value="1"
                                   <?php checked( ! empty( $options['toc_numbering'] ) ); ?>>
                            <span class="kashiwazaki-slider"></span>
                        </label>
                        <span class="kashiwazaki-setting-label" style="margin-bottom: 0;">番号を表示する</span>
                    </div>
                    <p class="kashiwazaki-setting-description">「1, 1.1, 1.2...」形式の番号を表示します。</p>
                </div>

                <div class="kashiwazaki-setting-group">
                    <div class="kashiwazaki-toggle">
                        <label class="kashiwazaki-switch">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( $this->option_name ); ?>[toc_show_toggle]"
                                   value="1"
                                   id="kashiwazaki-toc-show-toggle"
                                   <?php checked( ! empty( $options['toc_show_toggle'] ) ); ?>>
                            <span class="kashiwazaki-slider"></span>
                        </label>
                        <span class="kashiwazaki-setting-label" style="margin-bottom: 0;">開閉ボタンを表示する</span>
                    </div>
                </div>

                <div class="kashiwazaki-setting-group" id="kashiwazaki-toc-default-open-group" style="<?php echo empty( $options['toc_show_toggle'] ) ? 'display:none;' : ''; ?>">
                    <div class="kashiwazaki-toggle">
                        <label class="kashiwazaki-switch">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( $this->option_name ); ?>[toc_default_open]"
                                   value="1"
                                   <?php checked( ! empty( $options['toc_default_open'] ) ); ?>>
                            <span class="kashiwazaki-slider"></span>
                        </label>
                        <span class="kashiwazaki-setting-label" style="margin-bottom: 0;">デフォルトで開いた状態にする</span>
                    </div>
                    <p class="kashiwazaki-setting-description">オフにすると、目次は閉じた状態で表示されます。</p>
                </div>
                <script>
                (function() {
                    var toggleCheckbox = document.getElementById('kashiwazaki-toc-show-toggle');
                    var defaultOpenGroup = document.getElementById('kashiwazaki-toc-default-open-group');
                    if (toggleCheckbox && defaultOpenGroup) {
                        toggleCheckbox.addEventListener('change', function() {
                            defaultOpenGroup.style.display = this.checked ? '' : 'none';
                        });
                    }
                })();
                </script>
            </div>
        </div>

        <!-- スクロール設定 -->
        <div class="kashiwazaki-card">
            <div class="kashiwazaki-card-header">
                <span class="dashicons dashicons-arrow-down-alt"></span>
                <h3>スクロール設定</h3>
            </div>
            <div class="kashiwazaki-card-body">
                <div class="kashiwazaki-setting-group">
                    <div class="kashiwazaki-toggle">
                        <label class="kashiwazaki-switch">
                            <input type="checkbox"
                                   name="<?php echo esc_attr( $this->option_name ); ?>[toc_smooth_scroll]"
                                   value="1"
                                   <?php checked( ! empty( $options['toc_smooth_scroll'] ) ); ?>>
                            <span class="kashiwazaki-slider"></span>
                        </label>
                        <span class="kashiwazaki-setting-label" style="margin-bottom: 0;">スムーススクロールを有効にする</span>
                    </div>
                    <p class="kashiwazaki-setting-description">目次リンクをクリックした時に滑らかにスクロールします。</p>
                </div>

                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">スクロールオフセット</span>
                    <div class="kashiwazaki-inline-input">
                        <input type="number"
                               name="<?php echo esc_attr( $this->option_name ); ?>[toc_scroll_offset]"
                               value="<?php echo esc_attr( $options['toc_scroll_offset'] ); ?>"
                               min="0" max="500" class="small-text">
                        <span>px</span>
                    </div>
                    <p class="kashiwazaki-setting-description">
                        <strong>0</strong> = 固定ヘッダーの高さを自動検出<br>
                        <strong>1以上</strong> = 指定した値を使用（固定ヘッダーがある場合に設定）
                    </p>
                </div>
            </div>
        </div>

        <?php
    }

    /**
     * デザインタブをレンダリング
     *
     * @param array $options オプション
     */
    private function render_tab_design( $options ) {
        $color_schemes = array(
            'default' => array(
                'name'       => 'デフォルト（グレー）',
                'bg'         => '#f8f9fa',
                'header_bg'  => '#e9ecef',
                'border'     => '#dee2e6',
                'text'       => '#333',
                'link_hover' => '#007bff',
            ),
            'blue' => array(
                'name'       => 'ブルー',
                'bg'         => '#e3f2fd',
                'header_bg'  => '#bbdefb',
                'border'     => '#90caf9',
                'text'       => '#1565c0',
                'link_hover' => '#0d47a1',
            ),
            'green' => array(
                'name'       => 'グリーン',
                'bg'         => '#e8f5e9',
                'header_bg'  => '#c8e6c9',
                'border'     => '#a5d6a7',
                'text'       => '#2e7d32',
                'link_hover' => '#1b5e20',
            ),
            'orange' => array(
                'name'       => 'オレンジ',
                'bg'         => '#fff3e0',
                'header_bg'  => '#ffe0b2',
                'border'     => '#ffcc80',
                'text'       => '#e65100',
                'link_hover' => '#bf360c',
            ),
            'purple' => array(
                'name'       => 'パープル',
                'bg'         => '#f3e5f5',
                'header_bg'  => '#e1bee7',
                'border'     => '#ce93d8',
                'text'       => '#7b1fa2',
                'link_hover' => '#4a148c',
            ),
            'dark' => array(
                'name'       => 'ダーク',
                'bg'         => '#2d2d2d',
                'header_bg'  => '#3d3d3d',
                'border'     => '#444',
                'text'       => '#e0e0e0',
                'link_hover' => '#66b3ff',
            ),
        );

        $current_scheme = isset( $options['toc_color_scheme'] ) ? $options['toc_color_scheme'] : 'default';
        ?>
        <input type="hidden" name="<?php echo esc_attr( $this->option_name ); ?>[active_tab]" value="design">

        <!-- 配色設定 -->
        <div class="kashiwazaki-card">
            <div class="kashiwazaki-card-header">
                <span class="dashicons dashicons-art"></span>
                <h3>目次の配色</h3>
            </div>
            <div class="kashiwazaki-card-body">
                <div class="kashiwazaki-setting-group">
                    <span class="kashiwazaki-setting-label">カラースキーム</span>
                    <div class="kashiwazaki-color-scheme-grid">
                        <?php foreach ( $color_schemes as $key => $scheme ) : ?>
                            <label class="kashiwazaki-color-scheme-option <?php echo $current_scheme === $key ? 'selected' : ''; ?>">
                                <input type="radio"
                                       name="<?php echo esc_attr( $this->option_name ); ?>[toc_color_scheme]"
                                       value="<?php echo esc_attr( $key ); ?>"
                                       <?php checked( $current_scheme, $key ); ?>>
                                <span class="kashiwazaki-color-preview" style="background: <?php echo esc_attr( $scheme['bg'] ); ?>; border-color: <?php echo esc_attr( $scheme['border'] ); ?>;">
                                    <span class="preview-header" style="background: <?php echo esc_attr( $scheme['header_bg'] ); ?>; border-color: <?php echo esc_attr( $scheme['border'] ); ?>;"></span>
                                    <span class="preview-line" style="background: <?php echo esc_attr( $scheme['text'] ); ?>;"></span>
                                    <span class="preview-line short" style="background: <?php echo esc_attr( $scheme['text'] ); ?>;"></span>
                                    <span class="preview-line" style="background: <?php echo esc_attr( $scheme['text'] ); ?>;"></span>
                                </span>
                                <span class="kashiwazaki-scheme-name"><?php echo esc_html( $scheme['name'] ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="kashiwazaki-setting-description">目次の配色テーマを選択します。</p>
                </div>
            </div>
        </div>

        <style>
        .kashiwazaki-color-scheme-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .kashiwazaki-color-scheme-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 12px;
            border: 2px solid #dcdcde;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .kashiwazaki-color-scheme-option:hover {
            border-color: #2271b1;
            background: #f6f7f7;
        }

        .kashiwazaki-color-scheme-option.selected {
            border-color: #2271b1;
            background: #e7f3ff;
        }

        .kashiwazaki-color-scheme-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .kashiwazaki-color-preview {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: 60px;
            border: 1px solid;
            border-radius: 4px;
            overflow: hidden;
        }

        .kashiwazaki-color-preview .preview-header {
            height: 16px;
            border-bottom: 1px solid;
        }

        .kashiwazaki-color-preview .preview-line {
            height: 6px;
            margin: 6px 8px 0;
            border-radius: 2px;
            opacity: 0.7;
        }

        .kashiwazaki-color-preview .preview-line.short {
            width: 60%;
            margin-left: 16px;
        }

        .kashiwazaki-scheme-name {
            font-size: 12px;
            font-weight: 500;
            color: #1d2327;
        }

        @media screen and (max-width: 600px) {
            .kashiwazaki-color-scheme-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        </style>

        <script>
        (function() {
            var options = document.querySelectorAll('.kashiwazaki-color-scheme-option');
            options.forEach(function(option) {
                option.addEventListener('click', function() {
                    options.forEach(function(o) { o.classList.remove('selected'); });
                    this.classList.add('selected');
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * ヘルプタブをレンダリング
     */
    private function render_tab_help() {
        ?>
        <div class="kashiwazaki-help-section">
            <h2>基本的な使い方</h2>
            <ol>
                <li>投稿または固定ページの編集画面を開きます</li>
                <li>「Kashiwazaki SEO Headline Generator」メタボックスを探します</li>
                <li>「分析する」ボタンをクリックして見出しを分析します</li>
                <li>警告や提案を確認し、必要に応じて見出しを修正します</li>
                <li>「カニバリチェック」で他の記事との重複をチェックできます</li>
            </ol>
        </div>

        <div class="kashiwazaki-help-section">
            <h2>機能一覧</h2>
            <div class="kashiwazaki-feature-grid">
                <div class="kashiwazaki-feature-item">
                    <span class="dashicons dashicons-visibility"></span>
                    <div>
                        <h4>見出し構造表示</h4>
                        <p>投稿内の見出しを階層構造で視覚的に表示します</p>
                    </div>
                </div>
                <div class="kashiwazaki-feature-item">
                    <span class="dashicons dashicons-warning"></span>
                    <div>
                        <h4>階層構造バリデーション</h4>
                        <p>H2の次にH4が来るなどの階層飛びを検出します</p>
                    </div>
                </div>
                <div class="kashiwazaki-feature-item">
                    <span class="dashicons dashicons-editor-textcolor"></span>
                    <div>
                        <h4>文字数チェック</h4>
                        <p>長すぎる・短すぎる見出しを検出します</p>
                    </div>
                </div>
                <div class="kashiwazaki-feature-item">
                    <span class="dashicons dashicons-randomize"></span>
                    <div>
                        <h4>重複検出</h4>
                        <p>同一記事内で類似した見出しを検出します</p>
                    </div>
                </div>
                <div class="kashiwazaki-feature-item">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <div>
                        <h4>カニバリチェック</h4>
                        <p>公開済み記事との類似コンテンツを検出します</p>
                    </div>
                </div>
                <div class="kashiwazaki-feature-item">
                    <span class="dashicons dashicons-download"></span>
                    <div>
                        <h4>エクスポート</h4>
                        <p>見出し構造をテキストまたはCSVで出力できます</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="kashiwazaki-help-section">
            <h2>目次のショートコード</h2>
            <p>自動挿入をオフにした場合、以下のショートコードで任意の位置に目次を表示できます。</p>
            <div class="kashiwazaki-shortcode-box">
                <code>[kashiwazaki_toc]</code>
            </div>
            <p>タイトルをカスタマイズする場合：</p>
            <div class="kashiwazaki-shortcode-box">
                <code>[kashiwazaki_toc title="この記事の内容"]</code>
            </div>
        </div>
        <?php
    }
}
