<?php
/*
Plugin Name: LLM-Friendly Page Generator
Description: Generates LLM-friendly .md files for all WordPress pages and supports scheduled regeneration per post type.
Version: 1.3
Author: Damon Noisette
*/

defined('ABSPATH') or die('Na@SA33tzbdioraijoiueweiuwg!');

class LLM_Friendly_Page_Generator {

    private $plugin_slug = 'llm-friendly-regenerate';
    private $option_name = 'llm_regeneration_settings';

    public function __construct() {
        // Admin menu and settings
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Handle manual regeneration
        add_action('admin_init', array($this, 'handle_manual_regeneration'));

        // SpeedCache integration
        add_action('speedycache_clear_cache', array($this, 'rebuild_all_llm_pages'));

        // Schedule cron jobs
        add_action('init', array($this, 'setup_cron_jobs'));

        // Custom cron hook
        add_action('llm_friendly_scheduled_regeneration', array($this, 'scheduled_full_regeneration'));
    }

    /**
     * Add submenu under Tools
     */
    public function add_plugin_page() {
        add_submenu_page(
            'tools.php',
            'LLM Pages Manager',
            'LLM Pages',
            'manage_options',
            $this->plugin_slug,
            array($this, 'render_admin_page')
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>LLM-Friendly Page Generator</h1>
            <p>This tool allows you to manually or automatically regenerate LLM-friendly Markdown (.md) files.</p>

            <form method="post" action="options.php">
                <?php
                settings_fields($this->plugin_slug . '_group');
                do_settings_sections($this->plugin_slug);
                submit_button();
                ?>
            </form>

            <form method="post">
                <?php wp_nonce_field('llm_regenerate_action', 'llm_regenerate_nonce'); ?>
                <input type="submit" name="regenerate_md_files" class="button button-primary" value="Regenerate .md Files Now">
            </form>
        </div>
        <?php
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            $this->plugin_slug . '_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'llm_regeneration_section',
            'Scheduled Regeneration',
            array($this, 'settings_section_callback'),
            $this->plugin_slug
        );

        add_settings_field(
            'apply_to_all',
            'Apply Schedule to All Post Types',
            array($this, 'render_apply_to_all_field'),
            $this->plugin_slug,
            'llm_regeneration_section'
        );

        add_settings_field(
            'post_type_schedules',
            'Set Schedules by Post Type',
            array($this, 'render_post_type_schedule_fields'),
            $this->plugin_slug,
            'llm_regeneration_section'
        );
    }

    public function settings_section_callback() {
        echo '<p>Select how often Markdown files should be regenerated for each post type.</p>';
    }

    public function sanitize_settings($input) {
        $output = array();

        if (isset($input['apply_to_all']) && in_array($input['apply_to_all'], ['none', 'hourly', 'twicedaily', 'daily', 'weekly', 'monthly'])) {
            $output['apply_to_all'] = $input['apply_to_all'];
        }

        $post_types = get_post_types(['public' => true], 'objects');
        foreach ($post_types as $pt) {
            $key = 'schedule_' . $pt->name;
            if (isset($input[$key]) && in_array($input[$key], ['none', 'hourly', 'twicedaily', 'daily', 'weekly', 'monthly'])) {
                $output[$key] = $input[$key];
            }
        }

        return $output;
    }

    public function render_apply_to_all_field() {
        $settings = get_option($this->option_name);
        $selected = isset($settings['apply_to_all']) ? $settings['apply_to_all'] : 'daily';

        $intervals = ['none', 'hourly', 'twicedaily', 'daily', 'weekly', 'monthly'];
        echo "<select name='{$this->option_name}[apply_to_all]'>";
        foreach ($intervals as $interval) {
            echo "<option value='$interval'" . selected($selected, $interval, false) . ">$interval</option>";
        }
        echo "</select>";
    }

    public function render_post_type_schedule_fields() {
        $settings = get_option($this->option_name);
        $post_types = get_post_types(['public' => true], 'objects');

        echo "<table class='form-table'>";
        foreach ($post_types as $pt) {
            $key = 'schedule_' . $pt->name;
            $selected = isset($settings[$key]) ? $settings[$key] : 'daily';

            echo "<tr><th scope='row'>{$pt->label}</th><td>";
            echo "<select name='{$this->option_name}[$key]'>";
            foreach (['none', 'hourly', 'twicedaily', 'daily', 'weekly', 'monthly'] as $interval) {
                echo "<option value='$interval'" . selected($selected, $interval, false) . ">$interval</option>";
            }
            echo "</select></td></tr>";
        }
        echo "</table>";
    }

    /**
     * Handle manual regeneration
     */
    public function handle_manual_regeneration() {
        if (
            isset($_POST['regenerate_md_files']) &&
            isset($_POST['llm_regenerate_nonce']) &&
            wp_verify_nonce($_POST['llm_regenerate_nonce'], 'llm_regenerate_action')
        ) {
            $this->clear_existing_md_files();
            $this->generate_all_content();

            add_settings_error(
                'llm_messages',
                'llm_message',
                'Markdown files have been successfully regenerated.',
                'updated'
            );
        }

        settings_errors('llm_messages');
    }

    /**
     * Setup cron jobs based on saved intervals
     */
    public function setup_cron_jobs() {
        $settings = get_option($this->option_name);

        $apply_all = !empty($settings['apply_to_all']) ? $settings['apply_to_all'] : false;

        $post_types = get_post_types(['public' => true], 'objects');

        foreach ($post_types as $pt) {
            $key = 'schedule_' . $pt->name;
            $interval = $apply_all ?: (!empty($settings[$key]) ? $settings[$key] : 'daily');

            if ($interval === 'none') {
                $this->unschedule_post_type($pt->name);
            } else {
                $this->schedule_post_type($pt->name, $interval);
            }
        }
    }

    /**
     * Schedule a single post type
     */
    private function schedule_post_type($post_type, $interval) {
        $hook = "llm_friendly_scheduled_regeneration_{$post_type}";

        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), $interval, $hook);
            add_action($hook, function () use ($post_type) {
                $this->rebuild_llm_content_for_post_type($post_type);
            });
        }
    }

    /**
     * Unschedule a single post type
     */
    private function unschedule_post_type($post_type) {
        $hook = "llm_friendly_scheduled_regeneration_{$post_type}";
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }

    /**
     * Scheduled full regeneration handler
     */
    public function scheduled_full_regeneration() {
        $this->clear_existing_md_files();
        $this->generate_all_content();
    }

    /**
     * Regenerate content for a specific post type
     */
    public function rebuild_llm_content_for_post_type($post_type) {
        $this->generate_content_for_post_type($post_type);
    }

    /**
     * Clear existing .md files and llms.txt
     */
    public function clear_existing_md_files() {
        $llms_dir = WP_CONTENT_DIR . '/llms';
        if (file_exists($llms_dir)) {
            $this->delete_directory($llms_dir);
        }
    }

    /**
     * Recursively delete a directory and its contents
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) return;

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->delete_directory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    /**
     * Generate all content
     */
    public function generate_all_content() {
        $post_types = get_post_types(['public' => true], 'names');
        foreach ($post_types as $post_type) {
            $this->generate_content_for_post_type($post_type);
        }

        $this->generate_llms_txt();
    }

    /**
     * Generate content for a specific post type
     */
    private function generate_content_for_post_type($post_type) {
        $args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $url = get_permalink($post_id);
                $content = $this->convert_to_llm_friendly(get_the_content());

                $path = parse_url($url, PHP_URL_PATH);
                $path_parts = explode('/', trim($path, '/'));
                $filename = end($path_parts) ?: 'index';
                $dir_path = WP_CONTENT_DIR . '/llms' . ($path ? '/' . implode('/', array_slice($path_parts, 0, count($path_parts) - 1)) : '');

                if (!file_exists($dir_path)) {
                    wp_mkdir_p($dir_path);
                }

                $file_path = trailingslashit($dir_path) . $filename . '.md';
                file_put_contents($file_path, $content);
            }
        }
    }

    /**
     * Convert HTML to Markdown
     */
    private function convert_to_llm_friendly($html) {
        if (!class_exists('\League\HTMLToMarkdown\HtmlConverter')) {
            require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
        }

        $converter = new \League\HTMLToMarkdown\HtmlConverter();
        return $converter->convert($html);
    }

    /**
     * Generate /llms.txt file listing all .md files
     */
    private function generate_llms_txt() {
        $llms_txt_path = WP_CONTENT_DIR . '/llms.txt';

        $output = "# " . get_bloginfo('name') . "\n";
        $output .= "> " . get_bloginfo('description') . "\n\n";

        $post_types = get_post_types(['public' => true], 'objects');
        foreach ($post_types as $pt) {
            $pages = get_posts(array(
                'post_type'   => $pt->name,
                'post_status' => 'publish',
                'numberposts' => -1,
            ));

            foreach ($pages as $page) {
                $url = get_permalink($page->ID);
                $md_url = $url . '.md';
                $output .= "- [$page->post_title]($md_url)\n";
            }
        }

        file_put_contents($llms_txt_path, $output);
    }

    /**
     * Rebuild all LLM-friendly pages
     */
    public function rebuild_all_llm_pages() {
        $this->clear_existing_md_files();
        $this->generate_all_content();
    }
}

new LLM_Friendly_Page_Generator();