<?php
get_header();
?>

<section>
    <div class="container">
        <h1 style="text-align:center; margin:50px 0; font-size:32px;">Демонстрация виджета</h1>
        <?php if (is_active_sidebar('city-widget-area')) : ?>
            <div id="city-temperature-widget-area" style="display: flex; justify-content: center;">
                <?php dynamic_sidebar('city-widget-area'); ?>
            </div>
        <?php endif; ?>
        <a href="<?php echo home_url();?>/city-list/" class="tablelink">Открыть таблицу городов</a>
        
    </div>
</section>

<?php
get_footer();
?>