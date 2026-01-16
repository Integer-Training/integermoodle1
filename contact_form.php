<?php
require_once(__DIR__ . '/config.php');
require_login(); // Ensure user is logged in

$PAGE->set_url(new moodle_url('/contact.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title("Contact Admin");
$PAGE->set_heading("Contact Admin");

$success = false;
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    global $USER;

    $name    = fullname($USER); // Moodle fullname
    $email   = $USER->email;    // Moodle user email
    $userid  = $USER->id;       // Moodle user ID

    $subject = required_param('subject', PARAM_TEXT);
    $message = required_param('message', PARAM_TEXT);

    $to = "Janani@integertraining.com";  //info@integertraining.com

    $headers  = "From: " . $email . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    $body  = "A new contact request from Moodle:\n\n";
    $body .= "User ID: $userid\n";
    $body .= "Name: $name\n";
    $body .= "Email: $email\n\n";
    $body .= "Subject: $subject\n\n";
    $body .= "Message:\n$message\n";

    if (mail($to, $subject, $body, $headers)) {
        $success = true;
    } else {
        $error = "Mail could not be sent. Please contact admin.";
    }
}

echo $OUTPUT->header();

if ($success) {
    echo "<p style='color:green;'>✅ Thank you for contacting us, we will get back to you soon.</p>";
} else {
    if (!empty($error)) {
        echo "<p style='color:red;'>$error</p>";
    }
    ?>
    <form method="post" action="">
        <div>
            <label><strong>Name:</strong></label><br>
            <input type="text" value="<?php echo fullname($USER); ?>" readonly>
        </div><br>

        <div>
            <label><strong>Email:</strong></label><br>
            <input type="text" value="<?php echo $USER->email; ?>" readonly>
        </div><br>

        <div>
            <label><strong>Subject:</strong></label><br>
            <input type="text" name="subject" required>
        </div><br>

        <div>
            <label><strong>Message:</strong></label><br>
            <textarea name="message" rows="6" required></textarea>
        </div><br>

        <button type="submit">Send Message</button>
    </form>
    <?php
}

echo $OUTPUT->footer();
