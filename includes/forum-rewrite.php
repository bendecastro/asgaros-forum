<?php

if (!defined('ABSPATH')) exit;

class AsgarosForumRewrite {
    private static $asgarosforum = null;
    private static $usePermalinks = false;
    private static $links = array();

    public function __construct($object) {
		self::$asgarosforum = $object;

        // Check if permalinks are enabled.
        if (get_option('permalink_structure')) {
            self::$usePermalinks = true;
        }

        add_action('init', array($this, 'addRewriteRules'));
	}

    function addRewriteRules() {
        $post = get_post(self::$asgarosforum->options['location']);

        if ($post) {
            // TODO: Seems not work correctly when installing the first time because of flushing.
            add_rewrite_rule(
                '^'.preg_quote($post->post_name).'/([^/]*)/?$',
                'index.php?page_id='.$post->ID.'&view=$matches[1]',
                'top'
            );
            add_rewrite_rule(
                '^'.preg_quote($post->post_name).'/([^/]*)/([^/]*)/?$',
                'index.php?page_id='.$post->ID.'&view=$matches[1]&id=$matches[2]',
                'top'
            );

            add_rewrite_tag('%view%', '([^/]*)');
            add_rewrite_tag('%id%', '([^/]*)');
        }
    }

    // Builds and returns a requested link.
    public static function getLink($type, $elementID = false, $additionalParameters = false, $appendix = '') {
        // Only generate a link when that type is available.
        if (isset(self::$links[$type])) {
            $link = '';

            // Set an ID if available, otherwise initialize the base-link.
            if ($elementID) {
                if (self::$usePermalinks) {
                    $slug = self::getSlug($type, $elementID);
                    $link = self::$links[$type].$slug.'/';
                } else {
                    // When permalinks are disabled, the ID has to be used.
                    $link = add_query_arg('id', $elementID['id'], self::$links[$type]);
                }
            } else {
                $link = self::$links[$type];
            }

            // Set additional parameters if available, otherwise let the link unchanged.
            $link = ($additionalParameters) ? add_query_arg($additionalParameters, $link) : $link;

            // Return escaped URL with optional appendix at the end if set.
            return esc_url($link.$appendix);
        } else {
            return false;
        }
    }

    public static function setLinks() {
        global $wp;
        $links = array();
        $links['home']        = get_page_link(self::$asgarosforum->options['location']);

        if (self::$usePermalinks) {
            $links['home']        = trailingslashit($links['home']);
            $links['search']      = $links['home'].'search/';
            $links['forum']       = $links['home'].'forum/';
            $links['topic']       = $links['home'].'thread/';
            $links['topic_add']   = $links['home'].'addtopic/';
            $links['topic_move']  = $links['home'].'movetopic/';
            $links['post_add']    = $links['home'].'addpost/';
            $links['post_edit']   = $links['home'].'editpost/';
            $links['markallread'] = $links['home'].'markallread/';
        } else {
            $links['search']      = add_query_arg(array('view' => 'search'), $links['home']);
            $links['forum']       = add_query_arg(array('view' => 'forum'), $links['home']);
            $links['topic']       = add_query_arg(array('view' => 'thread'), $links['home']);
            $links['topic_add']   = add_query_arg(array('view' => 'addtopic'), $links['home']);
            $links['topic_move']  = add_query_arg(array('view' => 'movetopic'), $links['home']);
            $links['post_add']    = add_query_arg(array('view' => 'addpost'), $links['home']);
            $links['post_edit']   = add_query_arg(array('view' => 'editpost'), $links['home']);
            $links['markallread'] = add_query_arg(array('view' => 'markallread'), $links['home']);
        }

        $links['current']     = add_query_arg($_SERVER['QUERY_STRING'], '', trailingslashit(home_url($wp->request)));
        self::$links = $links;
    }

    public static function createUniqueSlug($name, $location) {
        $slug = sanitize_title($name);
        $slug = (is_numeric($slug)) ? 'forum-'.$slug : $slug;
        $existingSlugs = self::$asgarosforum->db->get_col("SELECT slug FROM ".$location." WHERE slug LIKE '".$slug."%';");

        if (count($existingSlugs) !== 0 && in_array($slug, $existingSlugs)) {
            $max = 1;
            while (in_array(($slug.'-'.++$max), $existingSlugs));
            $slug .= '-'.$max;
        }

        return $slug;
    }

    // Get the slug of an element.
    public static function getSlug($type, $elementID) {
        $availableSlugs = array('forum');

        if ($elementID) {
            if (in_array($type, $availableSlugs)) {
                $slug = false;

                if ($type === 'forum') {
                    $slug = self::$asgarosforum->db->get_var(self::$asgarosforum->db->prepare("SELECT slug FROM ".self::$asgarosforum->tables->forums." WHERE id = %d;", $elementID));
                }

                if (!empty($slug)) {
                    return $slug;
                }
            }

            return $elementID;
        }

        return false;
    }
}
