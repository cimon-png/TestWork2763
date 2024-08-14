<?php
/*
Template Name: Cities Table
*/

get_header();
?>
<section>
    <div class="container">
        <h1 style="text-align:center; margin: 50px 0;">Таблица городов</h1>
        <?php
        echo do_shortcode('[cities_table]'); 
        ?>
    </div>
</section>

<?php
get_footer();
?>