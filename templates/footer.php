</div><!-- סגירת content-inner -->
</div><!-- סגירת content -->
</div><!-- סגירת page-container -->

<!-- JavaScript לסרגל הצד ותפריט מובייל -->
<script src="<?php echo get_site_url(); ?>/assets/js/sidebar.js"></script>

<?php 
// לאפשר הכללה של קבצי JavaScript בסוף הדף
if (isset($additional_js_footer)) {
foreach ($additional_js_footer as $js_file) {
    echo '<script src="' . get_site_url() . '/assets/js/' . $js_file . '"></script>';
}
}
?>

</body>
</html>