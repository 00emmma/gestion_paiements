<?php
// includes/footer.php
?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Script pour le toggle de la sidebar sur mobile
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter un bouton toggle pour mobile
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth < 768) {
                sidebar.style.display = 'none';
            }
        });
    </script>
</body>
</html>