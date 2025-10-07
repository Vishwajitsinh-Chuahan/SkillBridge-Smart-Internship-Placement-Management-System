<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="row">
                <!-- About Section -->
                <div class="col-4">
                    <h5>SkillBridge</h5>
                    <p>Your ultimate internship and placement platform. Connect talented students with leading companies to bridge skills with career opportunities.</p>
                </div>
                
                <!-- Quick Links -->
                <div class="col-4">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/internships/browse.php">Browse Internships</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/companies/list.php">Companies</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/auth/register.php">Join as Student</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/companies/register.php">Partner with Us</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div class="col-4">
                    <h5>Contact Us</h5>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> info@skillbridge.com</p>
                        <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                        <div class="social-links">
                            <a href="#"><i class="fab fa-facebook"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Copyright -->
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> SkillBridge. All rights reserved.</p>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>

<!-- Additional JS if specified -->
<?php if(isset($additional_js)): ?>
    <script src="<?php echo $additional_js; ?>"></script>
<?php endif; ?>
</body>
</html>
