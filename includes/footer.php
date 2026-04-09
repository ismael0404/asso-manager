<?php
/**
 * Footer template v2.0
 * Fichier: includes/footer.php
 */
?>
    </main>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-grid">
                <div class="footer-section">
                    <h3><i class="fas fa-hands-helping"></i> AssocManager</h3>
                    <p>Plateforme de gestion des activités et membres de l'association. Ensemble, construisons un avenir meilleur.</p>
                    <div class="footer-social">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Liens Rapides</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/index.php">Accueil</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/user/activities.php">Activités</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#posts">Actualités</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/index.php#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <ul class="footer-contact">
                        <li><i class="fas fa-map-marker-alt"></i> Abidjan, Côte d'Ivoire</li>
                        <li><i class="fas fa-phone"></i> +225 07 00 00 00</li>
                        <li><i class="fas fa-envelope"></i> contact@association.com</li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Newsletter</h4>
                    <p>Abonnez-vous pour recevoir nos actualités.</p>
                    <form class="newsletter-form" onsubmit="event.preventDefault(); showNotification('Merci pour votre abonnement !', 'success');">
                        <input type="email" placeholder="Votre email..." required>
                        <button type="submit"><i class="fas fa-paper-plane"></i></button>
                    </form>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> AssocManager. Tous droits réservés. | Développé avec <i class="fas fa-heart" style="color: #e74c3c;"></i></p>
            </div>
        </div>
    </footer>
    
    <!-- Back to Top -->
    <button class="back-to-top" id="backToTop" aria-label="Retour en haut">
        <i class="fas fa-arrow-up"></i>
    </button>
    
    <script src="<?php echo BASE_URL; ?>/assets/js/script.js"></script>
</body>
</html>
