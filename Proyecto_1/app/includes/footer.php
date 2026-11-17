<!-- Ruta para que no se rompa con windows -->
 <?php
    $rutaAssets = str_contains($_SERVER['SCRIPT_NAME'], '/pages/')
        ? '../assets'
        : 'assets';
?>
    
    <!-- Bootstrap JavaScript -->
    <script src="<?= $rutaAssets ?>/js/bootstrap.bundle.min.js"></script>

</body>
</html>