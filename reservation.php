<?php
session_start();


require('include/db_config.php');
require('include/essentials.php');

// Handle AJAX request for recommendations
if (isset($_POST['action']) && $_POST['action'] === 'recommend') {
    $date = $_POST['date'];
    $numberOfPeople = (int)$_POST['numberOfPeople'];

    // Define time slots
    $time_slots = ['5PM', '6PM', '7PM', '8PM', '9PM'];

    // Fetch tables that can accommodate the given number of people
    $table_query = "SELECT id, capacity FROM tables WHERE capacity >= ?";
    $table_res = select($table_query, [$numberOfPeople], "i");
    $suitable_tables = [];
    while($row = mysqli_fetch_assoc($table_res)) {
        $suitable_tables[] = $row;
    }

    $recommendations = [];

    // For each time slot, find the least booked table
    foreach ($time_slots as $time) {
        $least_booked_table = null;
        $least_bookings = PHP_INT_MAX;

        foreach ($suitable_tables as $table) {
            $count_query = "SELECT COUNT(*) AS cnt FROM reservation WHERE date = ? AND arrival_time = ? AND table_number = ?";
            $count_res = select($count_query, [$date, $time, "Table ".$table['id']], "sss");
            $count_row = mysqli_fetch_assoc($count_res);
            $bookings = (int)$count_row['cnt'];

            if ($bookings < $least_bookings) {
                $least_bookings = $bookings;
                $least_booked_table = $table['id'];
            }
        }

        if ($least_booked_table !== null) {
            $recommendations[] = [
                'time' => $time,
                'table_id' => $least_booked_table,
                'bookings' => $least_bookings
            ];
        }
    }

    // Sort recommendations by least bookings
    usort($recommendations, function($a, $b) {
        return $a['bookings'] - $b['bookings'];
    });

    echo json_encode($recommendations);
    exit();
}

// Handle AJAX request for a featured reservation
if (isset($_POST['action']) && $_POST['action'] === 'featured') {
    // Fetch a featured reservation (e.g., the most recent one)
    $feat_query = "SELECT id, full_name, email, phone, date, arrival_time, number_of_people, table_number, notes FROM reservation ORDER BY id DESC LIMIT 1";
    $feat_res = select($feat_query, [], "");
    if ($feat_res && mysqli_num_rows($feat_res) > 0) {
        $feat_data = mysqli_fetch_assoc($feat_res);
        echo json_encode($feat_data);
    } else {
        echo json_encode([]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Naktya Chhen - Reservation</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php require('include/head-links.php')?> <!-- Ensure Bootstrap CSS is included -->
    </head>
    <body>
        <?php require('include/header.php')?>
        <main class="ph-reservation-page ph-innerpage">
            <section class="ph-banner">
                <div class="ph-banner__wrapper">
                    <div class="ph-banner__item">
                        <div class="ph-banner__item--img">
                            <img src="assets/img/banner.jpg" alt="">
                        </div>
                        <div class="ph-banner__item--content">
                            <div class="ph-banner__item--details">
                                <span class="ph-section__icon">
                                    <i class="fa fa-cutlery" aria-hidden="true"></i>
                                </span>
                                <h2>Taste Authentic Flavours</h2>
                                <p>Serving food with harmony since 2000</p>
                            </div>
                            <div class="ph-breadcrums"><div class="container">
                                <ul class="ph-breadcrums__list justify-content-center">
                                    <li class="item">
                                        <a href="index.php" title="Go to Home Page">Home</a>
                                    </li>
                                    <li class="item mt_page">
                                        <strong>Reservation</strong>
                                    </li>
                                </ul>
                            </div></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ph-reservation ph-section__padding-lg--tb">
                <div class="container">
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <!-- Recommendation Div (Bootstrap styled card) -->
                            <div id="recommendationDiv" class="card mb-4" style="display: none;">
                                <div class="card-body">
                                    <h4 class="card-title mb-3">Recommended Options</h4>
                                    <p class="mb-3" style="color: #39FF14;">Discover the perfect time and table for your dining experience at Naktya Chhen. We recommend the top option for you, but feel free to choose from others as well.</p>
                                    <ul id="recommendationList" class="list-group list-group-flush"></ul>
                                </div>
                            </div>

                            <div class="ph-reservation__form">
                                <!-- Original style for form fields -->
                                <div class="ph-section__title mb-5">
                                    <span class="ph-section__icon">
                                        <i class="fa fa-cutlery" aria-hidden="true"></i>
                                    </span>
                                    <h2>ALL DAY EXPERIENCE</h2>
                                    <h3>At and dinner, available every day.</h3>
                                </div>
                                <form method="POST" onsubmit="return validateReservationForm();">
                                    <div class="ph-input-wrapper">
                                        <input type="text" name="res_fname" id="res_fname" placeholder="Full Name" required>
                                    </div>
                                    <div class="ph-half">
                                        <div class="ph-input-wrapper">
                                            <input type="email" name="res_email" id="res_email" placeholder="example@email.com">
                                        </div> 
                                        <div class="ph-input-wrapper">
                                            <input type="phone" name="res_phone" id="res_phone" placeholder="+977 98********">
                                        </div>                                        
                                    </div>
                                    <div class="ph-half">
                                        <div class="ph-input-wrapper">
                                            <input type="date" name="date" id="dateBooking">
                                        </div>
                                        <div class="ph-input-wrapper">
                                            <select class="nice-select" name="arrivalTime" id="arrivalTimeBooking" style="display: none;">
                                                <option value="5PM" data-display="Arrival Time">5PM</option>
                                                <option value="6PM">6PM</option>
                                                <option value="7PM">7PM</option>
                                                <option value="8PM">8PM</option>
                                                <option value="9PM">9PM</option>
                                            </select>
                                            <div class="nice-select" tabindex="0">
                                                <span class="current">Arrival Time</span>
                                                <ul class="list">
                                                    <li data-value="5PM" class="option selected">5PM</li>
                                                    <li data-value="6PM" class="option">6PM</li>
                                                    <li data-value="7PM" class="option">7PM</li>
                                                    <li data-value="8PM" class="option">8PM</li>
                                                    <li data-value="9PM" class="option">9PM</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ph-half">
                                        <div class="ph-input-wrapper">
                                            <select class="nice-select" name="numberOfPeople" id="numberOfPeopleBooking" style="display: none;">
                                                <option value="1" data-display="No. of People">1 Person</option>
                                                <option value="2">2 Person</option>
                                                <option value="3">3 Person</option>
                                                <option value="4">4 Person</option>
                                                <option value="5">5 Person</option>
                                            </select>
                                            <div class="nice-select" tabindex="0">
                                                <span class="current">No. of People</span>
                                                <ul class="list">
                                                    <li data-value="1" class="option selected">1 Person</li>
                                                    <li data-value="2" class="option">2 Person</li>
                                                    <li data-value="3" class="option">3 Person</li>
                                                    <li data-value="4" class="option">4 Person</li>
                                                    <li data-value="5" class="option">5 Person</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="ph-input-wrapper">
                                            <select class="nice-select" name="tableNumber" id="tableNumberBooking" style="display: none;">
                                                <option value="Table 1" data-display="Table number">Table 1</option>
                                                <option value="Table 2">Table 2</option>
                                                <option value="Table 3">Table 3</option>
                                                <option value="Table 4">Table 4</option>
                                                <option value="Table 5">Table 5</option>
                                            </select>
                                            <div class="nice-select" tabindex="0">
                                                <span class="current">Table number</span>
                                                <ul class="list" id="tableOptionsList">
                                                    <li data-value="Table 1" class="option selected">Table 1</li>
                                                    <li data-value="Table 2" class="option">Table 2</li>
                                                    <li data-value="Table 3" class="option">Table 3</li>
                                                    <li data-value="Table 4" class="option">Table 4</li>
                                                    <li data-value="Table 5" class="option">Table 5</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                        
                                    <div class="ph-input-wrapper">
                                        <textarea placeholder="Additional notes..." name="notes" id="notesBooking"></textarea>
                                    </div>
                                    <div class="ph-input-wrapper">
                                        <input class="ph-btn ph-btn__form" type="submit" value="Submit" name="submit">
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <div class="ph-reservation__image">
                                <figure>
                                    <!-- Use Bootstrap to style the featured section on the side -->
                                    <div id="recommendationDescription" class="card" style="display:none;">
                                        <div class="card-header bg-info text-white">
                                            <h3 class="card-title h5 mb-0">Featured Reservation</h3>
                                        </div>
                                        <div class="card-body " id="recommendationText">
                                            No recommendation yet. Select a date and number of people to see suggestions.
                                        </div>
                                        <div class="card-footer">
                                            <p class="mb-0" style="color: #39FF14;">
                                                <strong>About Naktya Chhen:</strong><br>
                                                Indulge in authentic flavors, relish a warm ambiance, and enjoy our seasonal specialties sourced from local farms. Our trained chefs craft dishes that celebrate tradition and innovation. Relax in our inviting interiors while our dedicated staff ensures a remarkable dining experience.
                                            </p>
                                        </div>
                                    </div>
                                </figure>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </section>
            <section class="ph-component">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12 col-xl-6 g-0">
                            <div class="ph-component__img">
                                <figure>
                                    <img src="assets/img/reservation-feature.jpg" alt="">
                                </figure>
                            </div>
                        </div>
                        <div class="col-12 col-xl-6 g-0">
                            <div class="ph-component__content">
                                <div class="ph-section__title">
                                    <h2>Secure Your Journey Experience</h2>
                                    <h3>Plan Your Perfect Dining Experience</h3>
                                </div>
                                <div class="ph-section__content">
                                    <p style="color: #39FF14;">
                                        Secure your spot for an exceptional dining experience at Naktya Chhen. With limited availability, reserving your table ensures a seamless and memorable journey. Don't wait, book now and savor every moment with us.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php
           ini_set('display_errors', 1);
           ini_set('display_startup_errors', 1);
           error_reporting(E_ALL);

            if (isset($_POST['submit'])) {
                $frm_data = filteration($_POST);

                // Check for empty fields
                if (empty($frm_data['res_fname']) || empty($frm_data['res_email']) || empty($frm_data['res_phone']) || empty($frm_data['date']) || empty($frm_data['arrivalTime']) || empty($frm_data['numberOfPeople']) || empty($frm_data['tableNumber']) || empty($frm_data['notes'])) {
                    alert('error', 'Please fill out all the fields.');
                } else {
                    // Check for existing reservation
                    $existing_reservation_query = "SELECT * FROM reservation WHERE date = ? AND arrival_time = ? AND table_number = ?";
                    $existing_reservation_values = [
                        $frm_data['date'],
                        $frm_data['arrivalTime'],
                        $frm_data['tableNumber']
                    ];
                    $existing_reservation_res = select($existing_reservation_query, $existing_reservation_values, "sss");

                    if ($existing_reservation_res && mysqli_num_rows($existing_reservation_res) > 0) {
                        alert("error", "The reservation is already booked. Please select another time and date.");
                    } else {
                        // Insert new reservation
                        $query = "INSERT INTO reservation (full_name, email, phone, date, arrival_time, number_of_people, table_number, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $values = [
                            $frm_data['res_fname'],
                            $frm_data['res_email'],
                            $frm_data['res_phone'],
                            $frm_data['date'],
                            $frm_data['arrivalTime'],
                            $frm_data['numberOfPeople'],
                            $frm_data['tableNumber'],
                            $frm_data['notes']
                        ];
                        $res = insert($query, $values, "ssssssss");

                        if ($res == 1) {
                            alert('success', 'Reservation successful! Your table has been booked.');
                        } else {
                            alert('error', 'Error occurred! Please try again.');
                        }
                    }
                }
            }
            ?>

            <?php require('include/newsletter.php')?>
        </main>
        <?php require('include/footer.php')?>
        <?php require('include/script.php')?> <!-- Ensure Bootstrap JS is included -->

        <script>
        function validateReservationForm() {
            var resFname = document.getElementById('res_fname').value.trim();
            var resEmail = document.getElementById('res_email').value.trim();
            var resPhone = document.getElementById('res_phone').value.trim();
            var dateBooking = document.getElementById('dateBooking').value;
            var arrivalTimeBooking = document.getElementById('arrivalTimeBooking').value;
            var numberOfPeopleBooking = document.getElementById('numberOfPeopleBooking').value;
            var tableNumberBooking = document.getElementById('tableNumberBooking').value;
            var notesBooking = document.getElementById('notesBooking').value.trim();

            if (resFname === '' || resEmail === '' || resPhone === '' || dateBooking === '' || arrivalTimeBooking === '' || numberOfPeopleBooking === '' || tableNumberBooking === '') {
                alert('Please fill out all the fields.');
                return false;
            }

            var emailPattern = /^[^ ]+@[^ ]+\.[a-z]{2,3}$/;
            if (!resEmail.match(emailPattern)) {
                alert('Please enter a valid email address.');
                return false;
            }

            var phonePattern = /^\+?[0-9]{1,4}?[-.\s()]{0,2}?[0-9]{1,4}[-.\s]?[0-9]{1,9}$/;
            if (!resPhone.match(phonePattern)) {
                alert('Please enter a valid phone number.');
                return false;
            }

            return true;
        }

        const dateField = document.getElementById('dateBooking');
        const peopleField = document.getElementById('numberOfPeopleBooking');
        const recommendationDiv = document.getElementById('recommendationDiv');
        const recommendationList = document.getElementById('recommendationList');
        const recommendationDescription = document.getElementById('recommendationDescription');
        const recommendationText = document.getElementById('recommendationText');

        function loadRecommendations() {
            const dateVal = dateField.value;
            const peopleVal = peopleField.value;

            if (dateVal && peopleVal) {
                const formData = new FormData();
                formData.append('action', 'recommend');
                formData.append('date', dateVal);
                formData.append('numberOfPeople', peopleVal);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    recommendationList.innerHTML = '';
                    if (data.length > 0) {
                        // Highlight the best (first) recommendation more prominently using a Bootstrap card style
                        const best = data[0];
                        const bestLi = document.createElement('li');
                        bestLi.classList.add('list-group-item', 'border', 'border-success');
                        bestLi.innerHTML = `
                            <div class="d-flex align-items-center mb-2">
                                <span class="badge bg-success me-2">Best Option</span>
                                <span class="badge bg-primary me-2">${dateVal}</span>
                                <span class="badge bg-warning text-dark">Time: ${best.time}</span>
                            </div>
                            <p class="mb-1"><strong>Table:</strong> ${best.table_id}</p>
                            <p class="mb-1"><strong>Bookings:</strong> ${best.bookings}</p>
                            <small class="text-muted">Experience the best of Naktya Chhen at this perfect time slot!</small>
                        `;
                        recommendationList.appendChild(bestLi);

                        // Show other recommendations
                        data.slice(1).forEach(rec => {
                            const li = document.createElement('li');
                            li.classList.add('list-group-item');
                            li.innerHTML = `
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-secondary me-2">${dateVal}</span>
                                    <span class="badge bg-light text-dark">Time: ${rec.time}</span>
                                </div>
                                <p class="mb-1"><strong>Table:</strong> ${rec.table_id}</p>
                                <p class="mb-1"><strong>Bookings:</strong> ${rec.bookings}</p>
                            `;
                            recommendationList.appendChild(li);
                        });

                        recommendationDiv.style.display = 'block';
                        recommendationDescription.style.display = 'block';
                        recommendationText.innerHTML = `
                            <p style="color: #39FF14;">Below are recommended options tailored for your chosen date and group size. The top highlighted option is our best suggestion, but you can pick any other time and table that suits you. Enjoy our cozy ambiance, carefully selected ingredients, and a menu inspired by local flavors.</p>
                            <p style="color: #39FF14;"><strong >Did you know?</strong> We source our produce from trusted local farms, ensuring the freshest seasonal dishes. Every meal at Naktya Chhen is an opportunity to taste tradition and innovation in one bite!</p>
                        `;
                    } else {
                        recommendationList.innerHTML = '<li class="list-group-item">No recommendations available</li>';
                        recommendationDiv.style.display = 'block';
                        recommendationDescription.style.display = 'block';
                        recommendationText.textContent = "No suitable recommendations found for your selected criteria.";
                    }
                })
                .catch(err => {
                    console.log(err);
                    recommendationList.innerHTML = '<li class="list-group-item"></li>';
                    recommendationDiv.style.display = 'block';
                    recommendationDescription.style.display = 'block';
                    recommendationText.textContent = "";
                });

                // Fetch featured reservation
                fetchFeaturedReservation();
            } else {
                recommendationDiv.style.display = 'none';
                recommendationDescription.style.display = 'none';
            }
        }

        function fetchFeaturedReservation() {
            const formData = new FormData();
            formData.append('action', 'featured');
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if(res && res.id){
                    recommendationText.innerHTML += `
                        <div class="mt-4">
                            <h5>Featured Reservation:</h5>
                            <p><strong>Name:</strong> ${res.full_name}</p>
                            <p><strong>Email:</strong> ${res.email}</p>
                            <p><strong>Phone:</strong> ${res.phone}</p>
                            <p><strong>Date:</strong> ${res.date}</p>
                            <p><strong>Time:</strong> ${res.arrival_time}</p>
                            <p><strong>People:</strong> ${res.number_of_people}</p>
                            <p><strong>Table:</strong> ${res.table_number}</p>
                            <p><strong>Notes:</strong> ${res.notes || 'N/A'}</p>
                        </div>
                    `;
                } else {
                    recommendationText.innerHTML += "<p class='mt-4'>No featured reservation available at the moment.</p>";
                }
            })
            .catch(err => {
                console.log(err);
                recommendationText.innerHTML += "<p class='mt-4'></p>";
            });
        }

        dateField.addEventListener('change', loadRecommendations);
        peopleField.addEventListener('change', loadRecommendations);
        </script>
    </body>
</html>
