<?php

class ThemeAssets {
    public function __construct() {
        // Регистрируем хук для подключения стилей и скриптов
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_styles() {
        // Подключаем стили родительской темы
        wp_enqueue_style('parent-style', get_stylesheet_directory_uri() . '/style.css');
    }

    public function enqueue_scripts() {
        // Подключаем кастомные скрипты
        wp_enqueue_script('cities-table-ajax', get_stylesheet_directory_uri() . '/assets/js/cities-table.js', array('jquery'), null, true);
        
        // Локализация скриптов для передачи параметров из PHP в JS
        wp_localize_script('cities-table-ajax', 'cities_table_params', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
}

// Инициализация класса для подключения скриптов и стилей
$themeAssets = new ThemeAssets();



function create_cities_page() {
    // Автоматическое создание страницы для шаблона page-cities-table.php
    $page_title = 'city-list';
    $page = get_page_by_title($page_title);

    if (!$page) {
        // Если страницы нет, создаем новую
        $page_data = array(
            'post_title'    => $page_title,
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'page_template' => 'page-cities-table.php' // Название файла шаблона
        );

        // Создаем страницу
        $page_id = wp_insert_post($page_data);
    }
}

add_action('after_switch_theme', 'create_cities_page');



// Класс для работы с WeatherAPI
class WeatherAPIClient {
    private $api_key;

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    public function get_temperature($city_name) {
        $city_name_encoded = urlencode($city_name);
        $url = "http://api.weatherapi.com/v1/current.json?q=$city_name_encoded&key={$this->api_key}";

        $response = wp_remote_get($url, array('timeout' => 20));

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log("Weather API request failed for city: $city_name. Error: $error_message");
            return 'N/A';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!$data || isset($data->error)) {
            error_log("Weather API error for city: $city_name. Response: " . print_r($data, true));
            return 'N/A';
        }

        return isset($data->current->temp_c) ? round($data->current->temp_c, 1) : 'N/A';
    }
}

// Класс виджета
class CityTemperatureWidget extends WP_Widget {
    private $weather_api_client;

    public function __construct() {
        parent::__construct(
            'city_temperature_widget',
            'City Temperature',
            array('description' => 'Displays city temperature')
        );

        // Инициализация WeatherAPIClient
        $this->weather_api_client = new WeatherAPIClient('f635bd9a798b42fc9ff00546241408');
    }

    public function widget($args, $instance) {
        $city_id = !empty($instance['city_id']) ? $instance['city_id'] : '';
        if (!$city_id) return;

        $city_name = get_the_title($city_id);
        $temperature = $this->weather_api_client->get_temperature($city_name);

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html($city_name) . $args['after_title'];
        echo '<p>Температура: ' . esc_html($temperature) . '°C</p>';
        echo $args['after_widget'];
    }

    public function form($instance) {
        $city_id = !empty($instance['city_id']) ? $instance['city_id'] : '';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('city_id'); ?>">Select City:</label>
            <select id="<?php echo $this->get_field_id('city_id'); ?>" name="<?php echo $this->get_field_name('city_id'); ?>">
                <?php
                $cities = get_posts(array('post_type' => 'cities', 'posts_per_page' => -1));
                foreach ($cities as $city) {
                    echo '<option value="' . esc_attr($city->ID) . '" ' . selected($city->ID, $city_id, false) . '>' . esc_html($city->post_title) . '</option>';
                }
                ?>
            </select>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['city_id'] = (!empty($new_instance['city_id'])) ? strip_tags($new_instance['city_id']) : '';

        return $instance;
    }
}

// Класс таблицы городов
class CitiesTable {
    private $weather_api_client;

    public function __construct() {
        // Инициализация WeatherAPIClient
        $this->weather_api_client = new WeatherAPIClient('f635bd9a798b42fc9ff00546241408');
    }

    public function display() {
        // Custom action hook перед таблицей
        do_action('before_cities_table');

        // Поле поиска
        echo '<input type="text" id="city-search" placeholder="Найти город..." />';

        // Обертка для таблицы (для обновления через AJAX)
        echo '<div id="cities-table-container">';
        $this->render_table(); 
        echo '</div>';

        // Custom action hook после таблицы
        do_action('after_cities_table');
    }

    public function render_table($search_term = '') {
        global $wpdb;

        $cities = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title AS city, t.name AS country
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            LEFT JOIN {$wpdb->terms} t ON tr.term_taxonomy_id = t.term_id
            WHERE p.post_type = 'cities' AND p.post_status = 'publish' 
            AND p.post_title LIKE %s
        ", '%' . $wpdb->esc_like($search_term) . '%'));

        echo '<table>';
        echo '<thead><tr><th>Страна</th><th>Город</th><th>Температура (°C)</th></tr></thead>';
        echo '<tbody>';

        foreach ($cities as $city) {
            $temperature = $this->weather_api_client->get_temperature($city->city);
            echo '<tr>';
            echo '<td>' . esc_html($city->country) . '</td>';
            echo '<td>' . esc_html($city->city) . '</td>';
            echo '<td>' . esc_html($temperature) . '°C</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
    
    
    
    
}

// Custom action hook после таблицы (функция)
function display_homepage_link() {

    echo '<a href="'. home_url() . '" class="tablelink m50">Вернуться на главную</a>';
}
add_action('after_cities_table', 'display_homepage_link');



// AJAX обработчик для поиска
function cities_table_ajax_search() {
    $search_term = sanitize_text_field($_POST['search_term']);
    $cities_table = new CitiesTable();
    ob_start();
    $cities_table->render_table($search_term);
    $output = ob_get_clean();
    wp_send_json_success($output);
}

add_action('wp_ajax_cities_table_search', 'cities_table_ajax_search');
add_action('wp_ajax_nopriv_cities_table_search', 'cities_table_ajax_search');



// Класс для регистрации кастомного типа записи и таксономии
class CustomCityPostType {

    public function __construct() {
        add_action('init', array($this, 'register_cpt'));
        add_action('init', array($this, 'register_taxonomy'));
        add_action('add_meta_boxes', array($this, 'add_metaboxes'));
        add_action('save_post', array($this, 'save_city_coordinates'));
    }

    public function register_cpt() {
        $labels = array(
            'name' => 'Cities',
            'singular_name' => 'City',
            'menu_name' => 'Cities',
            'name_admin_bar' => 'City',
            'add_new' => 'Add New',
            'add_new_item' => 'Add New City',
            'new_item' => 'New City',
            'edit_item' => 'Edit City',
            'view_item' => 'View City',
            'all_items' => 'All Cities',
            'search_items' => 'Search Cities',
            'not_found' => 'No cities found.',
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-location-alt',
            'supports' => array('title', 'editor', 'custom-fields'),
            'show_in_rest' => true,
        );

        register_post_type('cities', $args);
    }

    public function register_taxonomy() {
        register_taxonomy('countries', 'cities', array(
            'labels' => array(
                'name'              => __('Countries'),
                'singular_name'     => __('Country'),
                'search_items'      => __('Search Countries'),
                'all_items'         => __('All Countries'),
                'parent_item'       => __('Parent Country'),
                'parent_item_colon' => __('Parent Country:'),
                'edit_item'         => __('Edit Country'),
                'update_item'       => __('Update Country'),
                'add_new_item'      => __('Add New Country'),
                'new_item_name'     => __('New Country Name'),
                'menu_name'         => __('Countries'),
            ),
            'hierarchical' => true, 
            'show_ui'      => true, 
            'show_in_menu' => true, 
            'show_in_rest' => true, 
            'rewrite'      => array('slug' => 'countries'),
        ));
    }

    public function add_metaboxes() {
        add_meta_box(
            'city_coordinates',
            'City Coordinates',
            array($this, 'render_city_coordinates_metabox'),
            'cities',
            'side',
            'default'
        );
    }

    public function render_city_coordinates_metabox($post) {
        $latitude = get_post_meta($post->ID, 'latitude', true);
        $longitude = get_post_meta($post->ID, 'longitude', true);

        ?>
        <label for="latitude">Latitude:</label>
        <input type="text" id="latitude" name="latitude" value="<?php echo esc_attr($latitude); ?>" />

        <label for="longitude">Longitude:</label>
        <input type="text" id="longitude" name="longitude" value="<?php echo esc_attr($longitude); ?>" />
        <?php
    }

    public function save_city_coordinates($post_id) {
        if (array_key_exists('latitude', $_POST)) {
            update_post_meta($post_id, 'latitude', $_POST['latitude']);
        }
        if (array_key_exists('longitude', $_POST)) {
            update_post_meta($post_id, 'longitude', $_POST['longitude']);
        }
    }
}

// Регистрация виджета
function register_city_temperature_widget() {
    register_widget('CityTemperatureWidget');
}

add_action('widgets_init', 'register_city_temperature_widget');

// Инициализация кастомного типа записи
$customCityPostType = new CustomCityPostType();

// Инициализация таблицы городов
function display_cities_table() {
    $cities_table = new CitiesTable();
    $cities_table->display();
}

add_shortcode('cities_table', 'display_cities_table');




//область для виджета
function mytheme_register_city_temperature_widget() {
    register_sidebar(array(
        'name'          => __('CityWidgetArea', 'mytheme'),
        'id'            => 'city-widget-area',
        'description'   => __('A widget area for displaying city temperatures in the center of the page.', 'mytheme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s" style="text-align: center;">', 
        'after_widget'  => '</div>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
}

add_action('widgets_init', 'mytheme_register_city_temperature_widget');
?>