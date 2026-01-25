<?php
/**
 * メタボックスクラス
 *
 * @package Kashiwazaki_SEO_Headline_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 投稿編集画面のメタボックスを管理するクラス
 */
class Kashiwazaki_SEO_Headline_Generator_Metabox {

    /**
     * アナライザーインスタンス
     *
     * @var Kashiwazaki_SEO_Headline_Generator_Analyzer
     */
    private $analyzer;

    /**
     * 設定インスタンス
     *
     * @var Kashiwazaki_SEO_Headline_Generator_Settings
     */
    private $settings;

    /**
     * コンストラクタ
     *
     * @param Kashiwazaki_SEO_Headline_Generator_Analyzer $analyzer アナライザーインスタンス
     * @param Kashiwazaki_SEO_Headline_Generator_Settings $settings 設定インスタンス
     */
    public function __construct( $analyzer, $settings ) {
        $this->analyzer = $analyzer;
        $this->settings = $settings;

        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
    }

    /**
     * メタボックスを追加
     */
    public function add_meta_box() {
        $options    = $this->settings->get_options();
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'kashiwazaki_seo_headline_metabox',
                __( 'Kashiwazaki SEO Headline Generator', 'kashiwazaki-seo-headline-generator' ),
                array( $this, 'render_meta_box' ),
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * メタボックスをレンダリング
     *
     * @param WP_Post $post 投稿オブジェクト
     */
    public function render_meta_box( $post ) {
        $options = $this->settings->get_options();
        ?>
        <div class="kashiwazaki-seo-headline-metabox">
            <!-- 操作ボタン -->
            <div class="kashiwazaki-seo-headline-actions">
                <button type="button" class="button button-primary" id="kashiwazaki-seo-headline-analyze">
                    <?php esc_html_e( '分析する', 'kashiwazaki-seo-headline-generator' ); ?>
                </button>
                <button type="button" class="button" id="kashiwazaki-seo-headline-check-cannibalization" disabled>
                    <?php esc_html_e( 'カニバリチェック', 'kashiwazaki-seo-headline-generator' ); ?>
                </button>
                <span class="kashiwazaki-seo-headline-export-buttons" style="display: none;">
                    <button type="button" class="button" id="kashiwazaki-seo-headline-copy-text">
                        <?php esc_html_e( 'テキストをコピー', 'kashiwazaki-seo-headline-generator' ); ?>
                    </button>
                    <button type="button" class="button" id="kashiwazaki-seo-headline-copy-csv">
                        <?php esc_html_e( 'CSVをコピー', 'kashiwazaki-seo-headline-generator' ); ?>
                    </button>
                    <button type="button" class="button" id="kashiwazaki-seo-headline-download-csv">
                        <?php esc_html_e( 'CSVダウンロード', 'kashiwazaki-seo-headline-generator' ); ?>
                    </button>
                </span>
                <span class="spinner" style="float: none; margin-top: 0;"></span>
            </div>

            <!-- 統計情報 -->
            <div class="kashiwazaki-seo-headline-stats" style="display: none;">
                <span class="stat-item">
                    <strong><?php esc_html_e( '見出し数:', 'kashiwazaki-seo-headline-generator' ); ?></strong>
                    <span id="kashiwazaki-seo-headline-count">0</span>
                </span>
                <span class="stat-item">
                    <strong><?php esc_html_e( '警告:', 'kashiwazaki-seo-headline-generator' ); ?></strong>
                    <span id="kashiwazaki-seo-headline-warning-count">0</span>
                </span>
            </div>

            <!-- 結果表示エリア -->
            <div class="kashiwazaki-seo-headline-results" style="display: none;">
                <!-- 見出し構造 -->
                <div class="kashiwazaki-seo-headline-section">
                    <h4><?php esc_html_e( '見出し構造', 'kashiwazaki-seo-headline-generator' ); ?></h4>
                    <div id="kashiwazaki-seo-headline-structure"></div>
                </div>

                <!-- 階層警告 -->
                <div class="kashiwazaki-seo-headline-section kashiwazaki-seo-headline-hierarchy-warnings" style="display: none;">
                    <h4>
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( '階層構造の警告', 'kashiwazaki-seo-headline-generator' ); ?>
                    </h4>
                    <div id="kashiwazaki-seo-headline-hierarchy-list"></div>
                </div>

                <!-- 文字数警告 -->
                <div class="kashiwazaki-seo-headline-section kashiwazaki-seo-headline-length-warnings" style="display: none;">
                    <h4>
                        <span class="dashicons dashicons-editor-textcolor"></span>
                        <?php esc_html_e( '文字数の警告', 'kashiwazaki-seo-headline-generator' ); ?>
                    </h4>
                    <div id="kashiwazaki-seo-headline-length-list"></div>
                </div>

                <!-- 重複警告 -->
                <div class="kashiwazaki-seo-headline-section kashiwazaki-seo-headline-duplicate-warnings" style="display: none;">
                    <h4>
                        <span class="dashicons dashicons-admin-page"></span>
                        <?php esc_html_e( '重複の警告', 'kashiwazaki-seo-headline-generator' ); ?>
                    </h4>
                    <div id="kashiwazaki-seo-headline-duplicate-list"></div>
                </div>

                <!-- カニバリゼーション警告 -->
                <div class="kashiwazaki-seo-headline-section kashiwazaki-seo-headline-cannibalization-warnings" style="display: none;">
                    <h4>
                        <span class="dashicons dashicons-networking"></span>
                        <?php esc_html_e( 'サイト内カニバリの警告', 'kashiwazaki-seo-headline-generator' ); ?>
                    </h4>
                    <div id="kashiwazaki-seo-headline-cannibalization-list"></div>
                </div>
            </div>

            <!-- 初期メッセージ -->
            <div class="kashiwazaki-seo-headline-initial-message">
                <p><?php esc_html_e( '「分析する」ボタンをクリックして、記事の見出し構造を分析します。', 'kashiwazaki-seo-headline-generator' ); ?></p>
            </div>

            <!-- 設定情報 -->
            <div class="kashiwazaki-seo-headline-settings-info">
                <small>
                    <?php
                    printf(
                        /* translators: %1$s: headline levels, %2$d: min length, %3$d: max length, %4$d: duplicate threshold, %5$d: cannibalization threshold */
                        esc_html__( '現在の設定: 対象見出し=%1$s / 推奨文字数=%2$d〜%3$d / 重複閾値=%4$d%% / カニバリ閾値=%5$d%%', 'kashiwazaki-seo-headline-generator' ),
                        esc_html( strtoupper( implode( ', ', $options['headline_levels'] ) ) ),
                        esc_html( $options['min_length'] ),
                        esc_html( $options['max_length'] ),
                        esc_html( $options['duplicate_threshold'] ),
                        esc_html( $options['cannibalization_threshold'] )
                    );
                    ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=kashiwazaki-seo-headline-generator' ) ); ?>" target="_blank">
                        <?php esc_html_e( '設定を変更', 'kashiwazaki-seo-headline-generator' ); ?>
                    </a>
                </small>
            </div>
        </div>
        <?php
    }
}
