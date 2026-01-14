<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <!-- About Section -->
            <div class="footer-section about">
                <h3>About SmartAngler</h3>
                <p>SmartAngler makes fishing competitions easy! Create and join tournaments, log your catches, and compete with anglers for great prizes!</p>
            </div>

            <!-- Quick Links Section -->
            <div class="footer-section links">
    <h3>Quick Links</h3>
    <ul>
        <li><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
        <li><a href="<?php echo SITE_URL; ?>/pages/tournament/tournaments.php">Tournaments</a></li>
        <li><a href="<?php echo SITE_URL; ?>/pages/calendar/calendar.php">Calendar</a></li>
    </ul>
</div>


            <!-- Contact Section -->
            <div class="footer-section contact">
                <h3>Contact</h3>
                <p><i class="fas fa-envelope"></i> alvina@smartangler.com</p>
                <p><i class="fas fa-phone"></i> +60 17-8373970</p>
                <p><i class="fas fa-map-marker-alt"></i> Sabah, Malaysia</p>

                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> SmartAngler | All rights reserved.</p>
        </div>
    </div>
</footer>

<style>
/* Footer Styles */
.footer {
    background-color: #0A4D68; /* deep ocean blue */
    color: #f8f6f0; /* light sand text */
    padding: 2rem 1rem 1rem 1rem;
    font-family: 'Segoe UI', sans-serif;
}

.footer a {
    color: #f8f6f0;
    text-decoration: none;
    transition: color 0.3s ease;
}

.footer a:hover {
    color: #05BFDB; /* teal highlight on hover */
}

.footer-content {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 2rem;
    max-width: 1200px;
    margin: auto;
}

.footer-section {
    flex: 1 1 250px;
}

.footer-section h3 {
    font-size: 1.25rem;
    margin-bottom: 1rem;
    color: #05BFDB;
    position: relative;
}

.footer-section h3::after {
    content: '';
    display: block;
    width: 50px;
    height: 2px;
    background: #05BFDB;
    margin-top: 5px;
    border-radius: 2px;
}

.footer-section p {
    font-size: 0.95rem;
    line-height: 1.6;
}

.footer-section ul {
    list-style: none;
    padding: 0;
}

.footer-section ul li {
    margin-bottom: 0.7rem;
}

.footer-section ul li a {
    font-size: 0.95rem;
}

.social-icons {
    margin-top: 1rem;
}

.social-icons a {
    display: inline-block;
    width: 36px;
    height: 36px;
    line-height: 36px;
    margin-right: 0.5rem;
    background-color: #088395;
    color: #fff;
    border-radius: 50%;
    text-align: center;
    transition: all 0.3s ease;
}

.social-icons a:hover {
    background-color: #05BFDB;
    transform: translateY(-3px);
}

.footer-bottom {
    text-align: center;
    margin-top: 2rem;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
    padding-top: 1rem;
    font-size: 0.85rem;
    color: rgba(248, 246, 240, 0.8);
}

/* Responsive */
@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        gap: 1.5rem;
    }
    .footer-section {
        flex: 1 1 100%;
    }
}
</style>
