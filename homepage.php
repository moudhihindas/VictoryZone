<?php
session_start();
// DB connection
include 'db_connect.php'; 

// Fetch logged-in user's profile picture for the nav icon
$nav_profile_pic = null;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $stmt = $conn->prepare("SELECT ProfilePic FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    //this is silent error handeling if db returns no pictures, the ?? ensures the vaiable stays null instead of crashing the page
    $nav_profile_pic = $row['ProfilePic'] ?? null;
}

// This holds the result after the form is submitted
$contact_message = "";

// Check if the contact form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["contact_submit"])) {

    // Get what the user typed in — trim() removes extra spaces
    $sender_name  = trim($_POST["sender_name"]);
    $sender_email = trim($_POST["sender_email"]);
    $message      = trim($_POST["message"]);

    // Make sure none of the fields are empty before saving
    if ($sender_name && $sender_email && $message) {

        // Save the message to the database safely using prepared statements
    
        $stmt = $conn->prepare("
            INSERT INTO contact_messages (sender_name, sender_email, message)
            VALUES (?, ?, ?) ");
          $stmt->bind_param("sss", $sender_name, $sender_email, $message);
          $stmt->execute();

        // Mark success so we can show a thank you message below
        $contact_message = "success";

    } else {
        // Something was empty — mark as error
        $contact_message = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VictoryZone - Home</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
   <link rel="stylesheet" href="VictoryCss.css">
   <link rel="stylesheet" href="H&F.css">
</head>
<body>

    <!-- Navigation Header  -->
    <div class="navbar">
        <div class="nav-container">
            <a href="index.html" class="logo-area">
                <img src="https://i.imgur.com/Pm7kYQg.png" alt="VictoryZone Logo" class="logo-img">
                <div class="brand">Victory<span>Zone</span></div>
            </a>
            
            <ul class="nav-links">
                <li><a href="HomePage.php" class="nav-link active">Home</a></li>
                <li><a href="news-page.php" class="nav-link">News</a></li>
                <li><a href="tournaments.php" class="nav-link">Tournaments</a></li>
                <li><a href="Teams.php" class="nav-link">Teams</a></li>
                <li><a href="Players.php" class="nav-link">Players</a></li>
                <li><a href="RankingsTable.php" class="nav-link">Rankings</a></li>
                <li><a href="LiveMatches.php" class="nav-link">Live</a></li>
                <li><a href="forum.php" class="nav-link">Forum</a></li>
                <li><a href="perchuses.php" class="nav-link">Perchuses</a></li>
            </ul>
            
            <!-- Profile Dropdown -->
            <div class="profile-container">
                <div class="profile-icon" id="profileIcon">
                    <?php if ($nav_profile_pic): ?>
                        <img src="<?php echo htmlspecialchars($nav_profile_pic); ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="UserProf.php" class="dropdown-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        User Profile
                    </a>
                    <a href="logout.php" class="dropdown-item">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Log Out
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- HOME PAGE -->
    <div class="page active" id="home">
        <!-- HERO SECTION -->
        <section class="hero">
            <div class="hero-content">
                <div class="hero-eyebrow">WELCOME TO VICTORYZONE</div>
                <h1 class="hero-title">Watch. <span>Connect.</span> Belong.</h1>
                <p class="hero-tagline">
                    From the field to the screen. Follow live matches, geek out in gaming forums, and connect with fans across sports and esports.
                </p>
                <div class="hero-cta-group">
				    <a href="#news" class="read-more-btn"><span>Latest News</span></a>
                    <a href="#tournaments" class="read-more-btn"><span>Watch Tournament</span></a>
                    <a href="forum.php" class="read-more-btn" style="background: #ff8c1a !important; border-color: #ff8c1a !important; color: #000 !important; box-shadow: 0 0 20px rgba(255, 140, 26, 0.6) !important;">
                    <span style="color: #000 !important;">Join The Fandom</span></a>
                    <a href="#Matches" class="read-more-btn"><span>Live Matches</span></a>
					<a href="#contact" class="read-more-btn"><span>Contact Us</span></a>

                </div>
            </div>
        </section>

        <!-- MAIN CONTENT CONTAINER -->
        <div class="container">

            <!-- NEWS SECTION -->
            <section class="section" id="news">
                <div class="section-header">
                    <h2 class="section-title">Latest <span class="title-accent">News</span></h2>
                    <a href="news-page.php" class="see-all-link">View All →</a>
                </div>
                <div class="cards-grid">
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?w=600&q=80" alt="Championship Finals" class="card-image">
                            <div class="card-tag">ESPORTS</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">March 5, 2026</div>
                            <h3 class="card-title">Championship Finals Break Viewership Records</h3>
                            <p class="card-excerpt">The VictoryZone Championship Finals attracted over 2 million concurrent viewers, setting a new platform record with intense matches and surprising upsets.</p>
                            <a href="#news/1" class="card-read-more">Read More
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M5 12h14M12 5l7 7-7 7" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1598550457678-aa60413d7c80?w=600&q=80" alt="Rising Star" class="card-image">
                            <div class="card-tag">PLAYER SPOTLIGHT</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">March 4, 2026</div>
                            <h3 class="card-title">Rising Star Takes Down Top-Ranked Champion</h3>
                            <p class="card-excerpt">Unknown player 'PhoenixRising' shocked the esports world by defeating the three-time champion in an intense best-of-five series.</p>
                            <a href="#news/2" class="card-read-more">Read More
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M5 12h14M12 5l7 7-7 7" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1511512578047-dfb367046420?w=600&q=80" alt="Platform Update" class="card-image">
                            <div class="card-tag">PLATFORM UPDATE</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">March 3, 2026</div>
                            <h3 class="card-title">New Tournament Formats Launching Next Season</h3>
                            <p class="card-excerpt">VictoryZone announces innovative tournament structures including team drafts, region-based competitions, and increased prize pools.</p>
                            <a href="#news/3" class="card-read-more">Read More
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M5 12h14M12 5l7 7-7 7" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TOURNAMENTS SECTION -->
            <section class="section" id="tournaments">
                <div class="section-header">
                    <h2 class="section-title">Featured <span class="title-accent">Tournaments</span></h2>
                    <a href="tournaments.php" class="see-all-link">View All →</a>
                </div>
                <div class="cards-grid">
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1542751371-adc38448a05e?w=600&q=80" alt="Winter Championship" class="card-image">
                            <div class="card-tag">OPEN</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">VALORANT</div>
                            <h3 class="card-title">Winter Championship 2026</h3>
                            <div class="tournament-meta">
                                <span class="meta-item">💰 $50,000</span>
                                <span class="meta-item">📅 Mar 15-20</span>
                                <span class="meta-item">👥 128 Teams</span>
                            </div>
                            <a href="tournaments.php" class="read-more-btn btn-sm full-width mt-sm"><span>Watch Tournament</span></a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1538481199705-c710c4e965fc?w=600&q=80" alt="Spring Invitational" class="card-image">
                            <div class="card-tag tag-live">🔴 LIVE</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">LEAGUE OF LEGENDS</div>
                            <h3 class="card-title">Spring Invitational</h3>
                            <div class="tournament-meta">
                                <span class="meta-item">💰 $75,000</span>
                                <span class="meta-item">📅 Live Now</span>
                                <span class="meta-item">👥 64 Teams</span>
                            </div>
                            <a href="tournaments.php" class="read-more-btn btn-sm full-width mt-sm"><span>Watch Live</span></a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1552820728-8b83bb6b773f?w=600&q=80" alt="Masters Series" class="card-image">
                            <div class="card-tag tag-upcoming">UPCOMING</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">CS2</div>
                            <h3 class="card-title">Masters Series: Week 4</h3>
                            <div class="tournament-meta">
                                <span class="meta-item">💰 $30,000</span>
                                <span class="meta-item">📅 Mar 22-25</span>
                                <span class="meta-item">👥 256 Teams</span>
                            </div>
                            <a href="tournaments.php" class="read-more-btn btn-sm full-width mt-sm"><span>Book Ticket</span></a>
                        </div>
                    </div>
                </div>
            </section>

            

            <!-- FEATURED Live Matches -->
            <section class="section" id="Matches">
                <div class="section-header">
                    <h2 class="section-title">Popular <span class="title-accent">Live Matches</span></h2>
                    <a href="LiveMatches.php" class="see-all-link">View All →</a>
                </div>
                <div class="cards-grid">
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1504450758481-7338eba7524a?w=600&q=80" alt="Basketball" class="card-image">
                            <div class="card-tag tag-live">🔴 LIVE</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">NBA</div>
                            <h3 class="card-title">Lakers vs Warriors</h3>
                            <p class="card-excerpt">Western Conference Finals Game 7 - Live from Crypto.com Arena</p>
                            <a href="LiveMatches.php" class="card-read-more">Watch Live →</a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1508098682722-e99c43a406b2?w=600&q=80" alt="Football" class="card-image">
                            <div class="card-tag">UPCOMING</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">UEFA CHAMPIONS LEAGUE</div>
                            <h3 class="card-title">Real Madrid vs Bayern Munich</h3>
                            <p class="card-excerpt">Semi-final first leg at Santiago Bernabéu</p>
                            <a href="LiveMatches.php" class="card-read-more">Buy Ticket →</a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-image-wrap">
                            <img src="https://images.unsplash.com/photo-1530641151443-8f0c9780590a?w=600&q=80" alt="Formula 1" class="card-image">
                            <div class="card-tag tag-live">🔴 LIVE</div>
                        </div>
                        <div class="card-body">
                            <div class="card-date">FORMULA 1</div>
                            <h3 class="card-title">Saudi Arabian Grand Prix</h3>
                            <p class="card-excerpt">Round 2 of the World Championship - Jeddah Corniche Circuit</p>
                            <a href="LiveMatches.php" class="card-read-more">Watch Live →</a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CONTACT US SECTION -->
            <section class="section" id="contact">
                <div class="section-header">
                    <h2 class="section-title">Contact <span class="title-accent">Us</span></h2>
                </div>
                <p class="section-subtitle">
                    Have questions or want to partner with us? Reach out — we'd love to hear from you.
                </p>

                <div class="contact-grid">
                    <!-- LEFT: Contact details + form -->
                    <div class="contact-info">
                        <!-- Address -->
                        <div class="contact-item">
                            <div class="contact-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div class="contact-detail">
                                <h4>Our Location</h4>
                                <p>VictoryZone HQ<br>350 Fifth Avenue, Suite 4200<br>New York, NY 10118, USA</p>
                            </div>
                        </div>
                        <!-- Phone -->
                        <div class="contact-item">
                            <div class="contact-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                </svg>
                            </div>
                            <div class="contact-detail">
                                <h4>Phone</h4>
                                <p>+1 (800) 842-8679</p>
                                <p style="font-size:0.85rem;opacity:0.7;">Mon–Fri, 9 AM – 6 PM EST</p>
                            </div>
                        </div>
                        <!-- Email -->
                        <div class="contact-item">
                            <div class="contact-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="contact-detail">
                                <h4>Email</h4>
                                <p>support@victoryzone.gg</p>
                                <p>partnerships@victoryzone.gg</p>
                            </div>
                        </div>

                        <?php
                        // Show success or error message right above the form
                        if ($contact_message === "success") {
                            echo "<div class='alert-box alert-success'>
                                    ✅ Thanks! Your message was received. We'll get back to you soon.
                                  </div>";
                        } elseif ($contact_message === "error") {
                            echo "<div class='alert-box alert-error'>
                                    ⚠️ Please fill in all fields before sending.
                                  </div>";
                        }
                        ?>

                        <!-- 
                            This is the contact form PHP POST form so it saves to the database.
                            The input names must match what PHP reads at the top of the file.
                        -->
                        <form class="contact-form" method="POST" action="HomePage.php#contact">

                            <!-- Hidden field so PHP knows this is the contact form -->
                            <input type="hidden" name="contact_submit" value="1">

                            <!-- Name field : was just placeholder before, now has a name attribute -->
                            <input type="text"
                                   name="sender_name"
                                   placeholder="Your Name"
                                   class="contact-input"
                                   required>

                            <!-- Email field -->
                            <input type="email"
                                   name="sender_email"
                                   placeholder="Your Email"
                                   class="contact-input"
                                   required>

                            <!-- Message field -->
                            <textarea rows="4"
                                      name="message"
                                      placeholder="Your Message"
                                      class="contact-input contact-textarea"
                                      required></textarea>

                            <!-- Submit button -->
                            <button type="submit"
                                    class="read-more-btn"
                                    style="width:100%;justify-content:center; min-width: auto;">
                                <span>Send Message</span>
                            </button>

                        </form>
                    </div>

                    <!-- RIGHT: Google Maps API  -->
                    <div class="contact-map">
                        <iframe
                            title="VictoryZone Office Location"
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3022.185701994908!2d-73.9878449!3d40.7484405!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c259a9b3117469%3A0xd134e199a405a163!2sEmpire%20State%20Building!5e0!3m2!1sen!2sus!4v1709999999999!5m2!1sen!2sus"
                            width="100%"
                            height="100%"
                            style="border:0; border-radius: 12px;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </section>

        </div>
    </div>

    <!-- FOOTER -->
    <footer class="site-footer">
        <div class="footer-inner">
            <div>
                <div class="footer-logo">
                    <img src="https://i.imgur.com/Pm7kYQg.png" alt="VictoryZone" class="footer-logo-img">
                    <span class="footer-logo-text">Victory<span class="logo-accent">Zone</span></span>
                </div>
                <p class="footer-about">
                    From the field to the screen. Follow live matches, geek out in gaming forums,
                    and connect with fans across sports and esports.
                </p>
                <div class="social-links">
                    <a href="https://www.facebook.com/" class="social-link" target="_blank" rel="noopener noreferrer">Facebook</a>
                    <a href="https://twitter.com/" class="social-link" target="_blank" rel="noopener noreferrer">Twitter</a>
                    <a href="https://discord.com/" class="social-link" target="_blank" rel="noopener noreferrer">Discord</a>
                    <a href="https://www.youtube.com/" class="social-link" target="_blank" rel="noopener noreferrer">YouTube</a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">Quick Links</h4>
                <div class="footer-links">
                    <a href="homepage.php">Home</a>
                    <a href="news-page.php">News</a>
                    <a href="tournaments.php">Tournaments</a>
                    <a href="Perchuses.php">Perchuses</a>
                </div>
            </div>
            <div>
                <h4 class="footer-heading">Support</h4>
                <div class="footer-links">
                    <a href="HomePage.php#contact">Help Center</a>
                    <button class="read-more-btn" style="padding: 3px; min-width: 10px;" onclick="openRulesModal()">Rules & Guidelines</button>
                  <button class="read-more-btn"style="padding: 3px; min-width: 10px;" onclick="openTermsModal()">Terms of Service</button>
                    
                </div>
            </div>
            <div>
    <h4 class="footer-heading">Newsletter</h4>
    <p class="footer-newsletter-text">
        Subscribe to get the latest news, tournament updates, and exclusive offers.
    </p>
    <form class="newsletter-form" id="newsletterForm" onsubmit="handleNewsletterSubmit(event)">
        <input type="email" id="newsletterEmail" class="newsletter-input" placeholder="Enter your email" required>
        <button type="submit" class="read-more-btn full-width" style="padding: 12px; min-width: auto;">
            <span>Subscribe</span>
        </button>
        <div id="newsletterSuccess" class="newsletter-success" style="display: none;">
            ✓ Thanks for subscribing!
        </div>
    </form>
</div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 VictoryZone. All rights reserved. Crafted with passion for dedicated gamers and sports enthusiasts everywhere.</p>
        </div>
    </footer>

    <!-- Toast Notification -->
    <div id="toast" class="toast" style="display: none;"></div>

// JS
    <script>
        // Profile Dropdown
        const profileIcon = document.getElementById('profileIcon');
        const profileDropdown = document.getElementById('profileDropdown');
// EVENT HANDLING: Click to show/hide profile menu
        if (profileIcon) {
            profileIcon.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });
        }

        document.addEventListener('click', (e) => {
            if (!profileIcon?.contains(e.target) && !profileDropdown?.contains(e.target)) {
                profileDropdown?.classList.remove('show');
            }
        });

        // Show toast notification
        function showToast(message) {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.style.display = 'block';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        // Newsletter submission
        function handleNewsletterSubmit(event) {
            event.preventDefault();
            const successDiv = document.getElementById('newsletterSuccess');
            if (successDiv) {
                successDiv.style.display = 'block';
                document.querySelector('.newsletter-input').value = '';
                setTimeout(() => {
                    successDiv.style.display = 'none';
                }, 5000);
            }
            showToast('Subscribed to newsletter!');
        }

        window.handleNewsletterSubmit = handleNewsletterSubmit;
        window.showToast = showToast;
		
		// ===================== SIMPLE POPUP FUNCTIONS =====================

// Close popup function
function closeLegalPopup() {
    const popup = document.getElementById('legalPopupContainer');
    if (popup) {
        popup.remove();
    }
    document.body.style.overflow = '';
}

// Open Terms of Service Popup
// Open Terms of Service Popup - FIXED GLASS VERSION
function openTermsModal() {
    // Remove existing if any
    const existing = document.getElementById('legalPopupContainer');
    if (existing) existing.remove();
    
    // Create popup container with inline styles
    const popupContainer = document.createElement('div');
    popupContainer.id = 'legalPopupContainer';
    popupContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(2, 6, 12, 0.6);
        -webkit-backdrop-filter: blur(8px);
        backdrop-filter: blur(8px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    // Create popup content
    popupContainer.innerHTML = `
        <div style="
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            background: rgba(2, 6, 12, 0.85);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border: 2px solid #ff8c1a;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0 50px rgba(255, 140, 26, 0.3);
        ">
            <div style="
                padding: 20px;
                border-bottom: 1px solid rgba(255, 140, 26, 0.3);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: rgba(0, 0, 0, 0.3);
            ">
                <h2 style="
                    color: #ff8c1a;
                    font-family: 'Orbitron', sans-serif;
                    margin: 0;
                    font-size: 1.5rem;
                ">📄 Terms of Service</h2>
                <button onclick="closeLegalPopup()" style="
                    background: none;
                    border: none;
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 28px;
                    cursor: pointer;
                    transition: all 0.3s;
                " onmouseover="this.style.color='#ff8c1a'; this.style.transform='rotate(90deg)'" onmouseout="this.style.color='rgba(255,255,255,0.7)'; this.style.transform='rotate(0deg)'">×</button>
            </div>
            <div style="
                padding: 20px;
                overflow-y: auto;
                max-height: calc(85vh - 80px);
                color: rgba(255, 255, 255, 0.8);
                font-family: 'Rajdhani', sans-serif;
                line-height: 1.6;
            ">
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 0 0 15px 0;">📜 1. Acceptance of Terms</h3>
                <p>By accessing VictoryZone, you agree to be bound by these Terms of Service. If you disagree with any part, please do not use our platform.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">👥 2. Account Registration</h3>
                <ul style="margin: 10px 0 15px 20px;">
                    <li>You must be at least 13 years old to create an account</li>
                    <li>Provide accurate and complete registration information</li>
                    <li>You are responsible for maintaining account security</li>
                    <li>Notify us immediately of unauthorized account access</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">💰 3. Purchases & Payments</h3>
                <p>All purchases made through VictoryZone are final unless otherwise stated. We reserve the right to modify prices or cancel orders due to errors.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">🔒 4. Privacy & Data</h3>
                <p>Your privacy is important to us. We collect and process personal data according to our Privacy Policy. By using VictoryZone, you consent to such processing.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📱 5. User Content</h3>
                <ul>
                    <li>You retain ownership of content you post</li>
                    <li>You grant VictoryZone license to use, modify, and display your content</li>
                    <li>You are responsible for content you submit</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">⚠️ 6. Prohibited Activities</h3>
                <ul>
                    <li>Unauthorized access to other accounts or systems</li>
                    <li>Uploading malicious code or viruses</li>
                    <li>Impersonating VictoryZone staff or other users</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">⚖️ 7. Intellectual Property</h3>
                <p>All content on VictoryZone including logos, designs, and graphics are property of VictoryZone.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">⛔ 8. Termination</h3>
                <p>We may terminate or suspend your account immediately for violations of these Terms.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📅 9. Changes to Terms</h3>
                <p>We reserve the right to modify these Terms at any time. Continued use of VictoryZone after changes constitutes acceptance of new Terms.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📧 10. Contact Us</h3>
                <p>Email: <strong style="color: #ff8c1a;">legal@victoryzone.gg</strong></p>
                <p><em style="color: #00f2ff;">Last Updated: March 2026</em></p>
            </div>
            <div style="
                padding: 15px 20px;
                border-top: 1px solid rgba(255, 140, 26, 0.3);
                display: flex;
                justify-content: flex-end;
                background: rgba(0, 0, 0, 0.3);
            ">
                <button onclick="closeLegalPopup()" style="
                    padding: 8px 25px;
                    background: transparent;
                    border: 1px solid #ff8c1a;
                    color: rgba(255, 255, 255, 0.7);
                    font-family: 'Orbitron', sans-serif;
                    font-size: 0.85rem;
                    cursor: pointer;
                    transition: all 0.3s;
                    transform: skew(-20deg);
                " onmouseover="this.style.background='#ff8c1a'; this.style.color='#000'; this.style.boxShadow='0 0 20px rgba(255,140,26,0.5)'" onmouseout="this.style.background='transparent'; this.style.color='rgba(255,255,255,0.7)'; this.style.boxShadow='none'">
                    <span style="display: inline-block; transform: skew(20deg);">Close</span>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(popupContainer);
    document.body.style.overflow = 'hidden';
}
// Open Rules & Guidelines Popup
function openRulesModal() {
    // Remove existing if any
    const existing = document.getElementById('legalPopupContainer');
    if (existing) existing.remove();
    
    // Create popup container with inline styles
    const popupContainer = document.createElement('div');
    popupContainer.id = 'legalPopupContainer';
    popupContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(2, 6, 12, 0.7);
        backdrop-filter: blur(8px);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    // Create popup content
    popupContainer.innerHTML = `
        <div style="
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            background: rgba(2, 6, 12, 0.9);
            backdrop-filter: blur(10px);
            border: 2px solid #ff8c1a;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 0 50px rgba(255, 140, 26, 0.3);
        ">
            <div style="
                padding: 20px;
                border-bottom: 1px solid rgba(255, 140, 26, 0.2);
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: rgba(0, 0, 0, 0.5);
            ">
                <h2 style="
                    color: #ff8c1a;
                    font-family: 'Orbitron', sans-serif;
                    margin: 0;
                    font-size: 1.5rem;
                ">📋 Rules & Guidelines</h2>
                <button onclick="closeLegalPopup()" style="
                    background: none;
                    border: none;
                    color: rgba(255, 255, 255, 0.7);
                    font-size: 28px;
                    cursor: pointer;
                    transition: all 0.3s;
                " onmouseover="this.style.color='#ff8c1a'; this.style.transform='rotate(90deg)'" onmouseout="this.style.color='rgba(255,255,255,0.7)'; this.style.transform='rotate(0deg)'">×</button>
            </div>
            <div style="
                padding: 20px;
                overflow-y: auto;
                max-height: calc(85vh - 80px);
                color: rgba(255, 255, 255, 0.7);
                font-family: 'Rajdhani', sans-serif;
                line-height: 1.6;
            ">
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 0 0 15px 0;">🎮 1. Community Guidelines</h3>
                <p>Welcome to VictoryZone! Our community is built on respect, fair play, and positive engagement. All members must follow these guidelines to ensure a safe and enjoyable experience for everyone.</p>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📝 2. Posting Rules</h3>
                <ul style="margin: 10px 0 15px 20px;">
                    <li><strong>No Spam:</strong> Repeated posting of similar content is prohibited.</li>
                    <li><strong>No Hate Speech:</strong> Discrimination, harassment, or offensive language is strictly forbidden.</li>
                    <li><strong>No Cheating:</strong> Sharing exploits, hacks, or cheating methods results in immediate ban.</li>
                    <li><strong>No NSFW Content:</strong> Mature or inappropriate content is not allowed.</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">🏆 3. Tournament Conduct</h3>
                <p>Participants in VictoryZone tournaments must:</p>
                <ul>
                    <li>Respect all players and officials</li>
                    <li>Follow tournament-specific rules and schedules</li>
                    <li>Report any violations to tournament admins</li>
                    <li>Accept final decisions made by tournament organizers</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">💬 4. Forum & Comments</h3>
                <ul>
                    <li>Stay on topic in discussion threads</li>
                    <li>No personal attacks or toxic behavior</li>
                    <li>Use constructive criticism when disagreeing</li>
                    <li>Respect moderators' decisions</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">⚠️ 5. Consequences of Violation</h3>
                <p>Violations may result in:</p>
                <ul>
                    <li>Warning notification</li>
                    <li>Temporary suspension (1-30 days)</li>
                    <li>Permanent account ban</li>
                    <li>Removal from tournaments</li>
                    <li>Legal action for severe violations</li>
                </ul>
                
                <h3 style="color: #00f2ff; font-family: 'Orbitron', sans-serif; margin: 20px 0 10px 0;">📞 6. Reporting Issues</h3>
                <p>To report violations, contact our support team at <strong style="color: #ff8c1a;">support@victoryzone.gg</strong></p>
            </div>
            <div style="
                padding: 15px 20px;
                border-top: 1px solid rgba(255, 140, 26, 0.2);
                display: flex;
                justify-content: flex-end;
                background: rgba(0, 0, 0, 0.5);
            ">
                <button onclick="closeLegalPopup()" style="
                    padding: 8px 25px;
                    background: transparent;
                    border: 1px solid #ff8c1a;
                    color: rgba(255, 255, 255, 0.7);
                    font-family: 'Orbitron', sans-serif;
                    font-size: 0.85rem;
                    cursor: pointer;
                    transition: all 0.3s;
                    transform: skew(-20deg);
                " onmouseover="this.style.background='#ff8c1a'; this.style.color='#000'; this.style.boxShadow='0 0 20px rgba(255,140,26,0.5)'" onmouseout="this.style.background='transparent'; this.style.color='rgba(255,255,255,0.7)'; this.style.boxShadow='none'">
                    <span style="display: inline-block; transform: skew(20deg);">Close</span>
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(popupContainer);
    document.body.style.overflow = 'hidden';
}

// Close with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLegalPopup();
    }
});

console.log('Popup functions ready!');
    </script>
</body>
</html>