<?php
/**
 * 見出し分析クラス
 *
 * @package Kashiwazaki_SEO_Headline_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 見出しの分析を行うクラス
 */
class Kashiwazaki_SEO_Headline_Generator_Analyzer {

    /**
     * コンテンツを分析
     *
     * @param string $content 投稿コンテンツ
     * @param string $title   投稿タイトル
     * @param array  $options オプション設定
     * @return array 分析結果
     */
    public function analyze( $content, $title, $options ) {
        $headlines = $this->extract_headlines( $content, $options['headline_levels'] );

        $result = array(
            'headlines'          => $headlines,
            'hierarchy_warnings' => $this->check_hierarchy( $headlines ),
            'length_warnings'    => $this->check_length( $headlines, $options['min_length'], $options['max_length'] ),
            'duplicate_warnings' => $this->check_duplicates( $headlines, $title, $options['duplicate_threshold'] ),
            'total_count'        => count( $headlines ),
        );

        return $result;
    }

    /**
     * 見出しを抽出
     *
     * @param string $content コンテンツ
     * @param array  $levels  抽出する見出しレベル
     * @return array 見出しリスト
     */
    public function extract_headlines( $content, $levels ) {
        $headlines = array();

        if ( empty( $content ) || empty( $levels ) ) {
            return $headlines;
        }

        // レベルをソートして正規表現用に準備
        $level_pattern = implode( '|', array_map( function( $level ) {
            return preg_quote( $level, '/' );
        }, $levels ) );

        // 見出しを抽出（ブロックエディタとクラシックエディタの両方に対応）
        $pattern = '/<(' . $level_pattern . ')([^>]*)>(.*?)<\/\1>/is';

        if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
            foreach ( $matches as $index => $match ) {
                $tag        = strtolower( $match[1][0] );
                $level      = intval( substr( $tag, 1 ) );
                $text       = wp_strip_all_tags( $match[3][0] );
                $text       = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
                $text       = trim( $text );
                $char_count = mb_strlen( $text, 'UTF-8' );

                $headlines[] = array(
                    'index'      => $index,
                    'tag'        => $tag,
                    'level'      => $level,
                    'text'       => $text,
                    'char_count' => $char_count,
                    'position'   => $match[0][1],
                );
            }
        }

        return $headlines;
    }

    /**
     * 階層構造をチェック
     *
     * @param array $headlines 見出しリスト
     * @return array 階層警告リスト
     */
    public function check_hierarchy( $headlines ) {
        $warnings = array();

        if ( count( $headlines ) < 2 ) {
            return $warnings;
        }

        for ( $i = 1; $i < count( $headlines ); $i++ ) {
            $prev_level = $headlines[ $i - 1 ]['level'];
            $curr_level = $headlines[ $i ]['level'];

            // 階層が2以上飛んでいる場合（例：H2→H4）
            if ( $curr_level > $prev_level + 1 ) {
                $warnings[] = array(
                    'index'        => $headlines[ $i ]['index'],
                    'current'      => $headlines[ $i ],
                    'previous'     => $headlines[ $i - 1 ],
                    'message'      => sprintf(
                        '%s の次に %s が来ています（%s が期待されます）',
                        strtoupper( $headlines[ $i - 1 ]['tag'] ),
                        strtoupper( $headlines[ $i ]['tag'] ),
                        'H' . ( $prev_level + 1 )
                    ),
                    'skipped_from' => $prev_level,
                    'skipped_to'   => $curr_level,
                );
            }
        }

        return $warnings;
    }

    /**
     * 文字数をチェック
     *
     * @param array $headlines  見出しリスト
     * @param int   $min_length 最小文字数
     * @param int   $max_length 最大文字数
     * @return array 文字数警告リスト
     */
    public function check_length( $headlines, $min_length, $max_length ) {
        $warnings = array();

        foreach ( $headlines as $headline ) {
            $char_count = $headline['char_count'];
            $issues     = array();

            if ( $char_count < $min_length ) {
                $issues[] = array(
                    'type'      => 'too_short',
                    'message'   => sprintf(
                        '短すぎます（%d文字 / 推奨: %d文字以上）',
                        $char_count,
                        $min_length
                    ),
                    'current'   => $char_count,
                    'threshold' => $min_length,
                );
            }

            if ( $char_count > $max_length ) {
                $issues[] = array(
                    'type'      => 'too_long',
                    'message'   => sprintf(
                        '長すぎます（%d文字 / 推奨: %d文字以下）',
                        $char_count,
                        $max_length
                    ),
                    'current'   => $char_count,
                    'threshold' => $max_length,
                );
            }

            if ( ! empty( $issues ) ) {
                $warnings[] = array(
                    'index'    => $headline['index'],
                    'headline' => $headline,
                    'issues'   => $issues,
                );
            }
        }

        return $warnings;
    }

    /**
     * 重複をチェック
     *
     * @param array  $headlines 見出しリスト
     * @param string $title     投稿タイトル
     * @param int    $threshold 類似度閾値（%）
     * @return array 重複警告リスト
     */
    public function check_duplicates( $headlines, $title, $threshold ) {
        $warnings        = array();
        $checked_pairs   = array();
        $all_texts       = array();

        // タイトルも比較対象に含める
        if ( ! empty( $title ) ) {
            $all_texts[] = array(
                'index' => -1,
                'text'  => $title,
                'tag'   => 'title',
                'level' => 0,
            );
        }

        // 見出しを追加
        foreach ( $headlines as $headline ) {
            $all_texts[] = array(
                'index' => $headline['index'],
                'text'  => $headline['text'],
                'tag'   => $headline['tag'],
                'level' => $headline['level'],
            );
        }

        // 全ペアをチェック
        $count = count( $all_texts );
        for ( $i = 0; $i < $count; $i++ ) {
            for ( $j = $i + 1; $j < $count; $j++ ) {
                $text1 = $all_texts[ $i ]['text'];
                $text2 = $all_texts[ $j ]['text'];

                // 空のテキストはスキップ
                if ( empty( $text1 ) || empty( $text2 ) ) {
                    continue;
                }

                $similarity = $this->calculate_similarity( $text1, $text2 );

                if ( $similarity >= $threshold ) {
                    $warnings[] = array(
                        'item1'      => $all_texts[ $i ],
                        'item2'      => $all_texts[ $j ],
                        'similarity' => $similarity,
                        'message'    => sprintf(
                            '「%s」と「%s」が類似しています（類似度: %d%%）',
                            mb_strimwidth( $text1, 0, 30, '...', 'UTF-8' ),
                            mb_strimwidth( $text2, 0, 30, '...', 'UTF-8' ),
                            $similarity
                        ),
                    );
                }
            }
        }

        return $warnings;
    }

    /**
     * 類似度を計算
     *
     * @param string $str1 文字列1
     * @param string $str2 文字列2
     * @return int 類似度（%）
     */
    public function calculate_similarity( $str1, $str2 ) {
        // 正規化（小文字化、空白除去）
        $str1 = mb_strtolower( trim( $str1 ), 'UTF-8' );
        $str2 = mb_strtolower( trim( $str2 ), 'UTF-8' );

        if ( $str1 === $str2 ) {
            return 100;
        }

        if ( empty( $str1 ) || empty( $str2 ) ) {
            return 0;
        }

        // similar_textを使用して類似度を計算
        similar_text( $str1, $str2, $percent );

        return round( $percent );
    }

    /**
     * カニバリゼーションをチェック
     *
     * @param array  $headlines       現在の記事の見出しリスト
     * @param string $title           現在の記事タイトル
     * @param int    $current_post_id 現在の投稿ID
     * @param int    $threshold       類似度閾値（%）
     * @param array  $options         オプション設定
     * @return array カニバリゼーション警告リスト
     */
    public function check_cannibalization( $headlines, $title, $current_post_id, $threshold, $options ) {
        $warnings = array();

        // 現在の記事のテキストリストを作成
        $current_texts = array();

        if ( ! empty( $title ) ) {
            $current_texts[] = array(
                'type' => 'title',
                'text' => $title,
                'tag'  => 'h1',
            );
        }

        foreach ( $headlines as $text ) {
            if ( ! empty( $text ) ) {
                $current_texts[] = array(
                    'type' => 'headline',
                    'text' => $text,
                    'tag'  => '',
                );
            }
        }

        if ( empty( $current_texts ) ) {
            return $warnings;
        }

        // 公開済み記事を取得
        $post_types = isset( $options['post_types'] ) ? $options['post_types'] : array( 'post', 'page' );

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'post__not_in'   => array( $current_post_id ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        $posts = get_posts( $args );

        foreach ( $posts as $post ) {
            $post_title    = $post->post_title;
            $post_content  = $post->post_content;
            $post_headlines = $this->extract_headlines( $post_content, $options['headline_levels'] );
            $edit_link     = get_edit_post_link( $post->ID, 'raw' );

            // タイトル同士を比較
            foreach ( $current_texts as $current ) {
                // 現在のタイトルと他記事のタイトルを比較
                $similarity = $this->calculate_similarity( $current['text'], $post_title );

                if ( $similarity >= $threshold ) {
                    $warnings[] = array(
                        'current_text'    => $current['text'],
                        'current_type'    => $current['type'],
                        'matched_text'    => $post_title,
                        'matched_type'    => 'title',
                        'matched_post_id' => $post->ID,
                        'matched_title'   => $post_title,
                        'edit_link'       => $edit_link,
                        'similarity'      => $similarity,
                        'message'         => sprintf(
                            '「%s」が「%s」（ID: %d）のタイトルと類似しています（類似度: %d%%）',
                            mb_strimwidth( $current['text'], 0, 25, '...', 'UTF-8' ),
                            mb_strimwidth( $post_title, 0, 25, '...', 'UTF-8' ),
                            $post->ID,
                            $similarity
                        ),
                    );
                }

                // 現在のテキストと他記事の見出しを比較
                foreach ( $post_headlines as $post_headline ) {
                    $similarity = $this->calculate_similarity( $current['text'], $post_headline['text'] );

                    if ( $similarity >= $threshold ) {
                        $warnings[] = array(
                            'current_text'      => $current['text'],
                            'current_type'      => $current['type'],
                            'matched_text'      => $post_headline['text'],
                            'matched_type'      => 'headline',
                            'matched_tag'       => $post_headline['tag'],
                            'matched_post_id'   => $post->ID,
                            'matched_title'     => $post_title,
                            'edit_link'         => $edit_link,
                            'similarity'        => $similarity,
                            'message'           => sprintf(
                                '「%s」が「%s」（ID: %d）の見出し「%s」と類似しています（類似度: %d%%）',
                                mb_strimwidth( $current['text'], 0, 20, '...', 'UTF-8' ),
                                mb_strimwidth( $post_title, 0, 20, '...', 'UTF-8' ),
                                $post->ID,
                                mb_strimwidth( $post_headline['text'], 0, 20, '...', 'UTF-8' ),
                                $similarity
                            ),
                        );
                    }
                }
            }
        }

        // 類似度の高い順にソート
        usort( $warnings, function( $a, $b ) {
            return $b['similarity'] - $a['similarity'];
        } );

        return $warnings;
    }
}
