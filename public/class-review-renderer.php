<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TrustBio_Review_Renderer {

    public function render( int $post_id ): string {
        $date = get_post_meta( $post_id, '_trustbio_review_date', true );
        if ( ! $date ) return '';

        $formatted_date  = date_i18n( get_option( 'date_format' ), strtotime( $date ) );
        $raw_date        = $date;
        $reviewer_id     = (int) get_post_meta( $post_id, '_trustbio_reviewer_id', true );
        $reviewer        = $reviewer_id ? trustbio_get_author( $reviewer_id ) : null;

        $post_meta_label = get_post_meta( $post_id, '_trustbio_review_label', true );

        $bar_class       = new TrustBio_Review_Bar();
        $per_post_bar_id = (int) get_post_meta( $post_id, '_trustbio_review_bar_id', true );
        $bar = $per_post_bar_id ? $bar_class->get_box( $per_post_bar_id ) : null;
        if ( ! $bar ) {
            $bar = $bar_class->get_default_bar();
        }

        // Build combined credentials: plain text field + structured badge labels.
        $reviewer_credentials = $this->get_reviewer_credentials( $reviewer );

        if ( ! $bar ) {
            $label = $post_meta_label ?: get_option( 'trustbio_review_default_label', __( 'Medically reviewed by', 'trustbio' ) );
            $label = (string) apply_filters( 'trustbio_review_label', $label, $post_id );
            return $this->render_fallback( $reviewer, $label, $formatted_date, $raw_date, $reviewer_credentials );
        }

        $layout   = $bar->layout ?? 'classic';
        $settings = is_array( $bar->settings ) ? $bar->settings : $bar_class->get_default_settings( $layout );
        $accent   = sanitize_hex_color( $settings['accent_color'] ?? '' ) ?: '#4f46e5';

        $label = $post_meta_label;
        if ( ! $label && ! empty( $settings['label_text'] ) ) {
            $label = $settings['label_text'];
        }
        if ( ! $label ) {
            $label = get_option( 'trustbio_review_default_label', __( 'Medically reviewed by', 'trustbio' ) );
        }
        $label = (string) apply_filters( 'trustbio_review_label', $label, $post_id );

        $reviewer_avatar_url = '';
        if ( $reviewer && ! empty( $settings['show_avatar'] ) ) {
            $reviewer_avatar_url = $this->get_avatar_url( $reviewer );
        }

        /**
         * Filters the directory a review bar layout's template is loaded from.
         *
         * The TrustBio Pro add-on points its premium bar layouts at its own
         * template directory here.
         *
         * @since 1.0.0
         * @param string $dir    Directory with a trailing slash.
         * @param string $layout Layout slug being resolved.
         */
        $trustbio_dir      = (string) apply_filters(
            'trustbio_review_bar_template_dir',
            TRUSTBIO_PLUGIN_DIR . 'public/templates/review/',
            $layout
        );
        $trustbio_template = $trustbio_dir . 'bar-' . $layout . '.php';

        // Classic always ships with the free plugin, so a bar saved with an
        // add-on layout still renders when the add-on is no longer present.
        if ( ! file_exists( $trustbio_template ) ) {
            $trustbio_template = TRUSTBIO_PLUGIN_DIR . 'public/templates/review/bar-classic.php';
        }

        ob_start();
        include $trustbio_template;
        $html = ob_get_clean();

        wp_enqueue_style( 'trustbio-review-bars', TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-review-bars.css', array(), TRUSTBIO_VERSION );

        return (string) apply_filters( 'trustbio_review_bar_html', $html, $post_id );
    }

    /**
     * Build the combined credentials string: plain text field + structured badge labels.
     */
    private function get_reviewer_credentials( ?object $reviewer ): string {
        if ( ! $reviewer ) {
            return '';
        }
        $parts = array();
        if ( ! empty( $reviewer->credentials ) ) {
            $parts[] = trim( (string) $reviewer->credentials );
        }
        if ( isset( $reviewer->id ) && class_exists( 'TrustBio_Credentials' ) ) {
            $badge_text = TrustBio_Credentials::get_text_for_author( (int) $reviewer->id );
            if ( $badge_text ) {
                $parts[] = $badge_text;
            }
        }
        return implode( ', ', array_filter( $parts ) );
    }

    private function get_avatar_url( object $reviewer ): string {
        if ( ! empty( $reviewer->avatar_url ) ) {
            return $reviewer->avatar_url;
        }
        if ( ! empty( $reviewer->wp_user_id ) ) {
            return get_avatar_url( $reviewer->wp_user_id, [ 'size' => 80 ] ) ?: '';
        }
        if ( ! empty( $reviewer->email ) ) {
            return get_avatar_url( $reviewer->email, [ 'size' => 80 ] ) ?: '';
        }
        return '';
    }

    private function render_fallback( ?object $reviewer, string $label, string $formatted_date, string $raw_date, string $reviewer_credentials = '' ): string {
        $color = sanitize_hex_color( get_option( 'trustbio_review_bar_color', '#4f46e5' ) ) ?: '#4f46e5';
        $style = '--tbr-color:' . $color . ';';
        $html  = '<div class="tbr-bar tbr-classic" style="' . esc_attr( $style ) . '" itemscope itemtype="https://schema.org/Review">';
        $html .= '<span class="tbr-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg></span>';
        $display_label = $reviewer ? $label : trim( preg_replace( '/\s+by\s*$/iu', '', $label ) );
        $html .= '<span class="tbr-text">' . esc_html( $display_label ) . ' ';
        if ( $reviewer ) {
            $url   = trustbio_get_author_url( $reviewer );
            $html .= '<a href="' . esc_url( $url ) . '" class="tbr-name" rel="author">' . esc_html( $reviewer->display_name ) . '</a>';
            if ( $reviewer_credentials ) {
                $html .= ', <span class="tbr-creds">' . esc_html( $reviewer_credentials ) . '</span>';
            }
        }
        $html .= ' ' . esc_html__( 'on', 'trustbio' ) . ' ';
        $html .= '<time class="tbr-date" datetime="' . esc_attr( $raw_date ) . '">' . esc_html( $formatted_date ) . '</time>';
        $html .= '</span></div>';
        wp_enqueue_style( 'trustbio-review-bars', TRUSTBIO_PLUGIN_URL . 'public/css/trustbio-review-bars.css', array(), TRUSTBIO_VERSION );
        return $html;
    }
}
