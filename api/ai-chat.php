<?php
// ============================================================
// ALMS — AI Chat Endpoint with Fallback RAG Matcher
// ============================================================
require_once __DIR__ . '/../config.php';
apiCors();

if (!isAuthenticated()) {
    apiJson(['success' => false, 'message' => 'Unauthorized access.'], 401);
}
verifyCsrfFromRequest();

// Read JSON input
$input = readJsonInput();
$message = trim($input['message'] ?? '');

if (empty($message)) {
    apiJson(['success' => false, 'message' => 'Message is empty.'], 422);
}

$db = db();

// 1. Fetch Student Profile
$stmt = $db->prepare('SELECT vark_style, current_pace FROM student_profiles WHERE user_id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch();

$vark = $profile['vark_style'] ?? 'r';
$pace = $profile['current_pace'] ?? 'standard';

// 2. Fetch Latest Wellness Log
$wellnessStmt = $db->prepare('SELECT attention_span, stress_level FROM student_wellness_logs WHERE user_id = ? ORDER BY logged_at DESC LIMIT 1');
$wellnessStmt->execute([$_SESSION['user_id']]);
$wellness = $wellnessStmt->fetch();

$attention = $wellness['attention_span'] ?? 'medium';
$stress = $wellness['stress_level'] ?? 'low';

// 3. Search RAG Database Context (Course Materials)
// Clean and extract simple keywords from the message
$clean_msg = preg_replace('/[^a-zA-Z0-9\s]/', '', $message);
$words = explode(' ', $clean_msg);
$keywords = [];
foreach ($words as $w) {
    $w = trim($w);
    if (strlen($w) > 3) {
        $keywords[] = strtolower($w);
    }
}

$matched_material = null;
if (!empty($keywords)) {
    // Search materials using LIKE clauses
    $query = "SELECT title, content_text FROM course_materials WHERE ";
    $conditions = [];
    $params = [];
    foreach ($keywords as $k) {
        $conditions[] = "(title LIKE ? OR content_text LIKE ?)";
        $params[] = "%$k%";
        $params[] = "%$k%";
    }
    $query .= implode(" OR ", $conditions) . " LIMIT 1";

    $searchStmt = $db->prepare($query);
    $searchStmt->execute($params);
    $matched_material = $searchStmt->fetch();
}

// 4. Generate Adaptive Response
$response_text = "";

// Check if user is asking for general wellness support
$wellness_keywords = ['stress', 'tired', 'burnt', 'exhausted', 'overwhelm', 'anxious', 'scared', 'sad', 'wellness', 'depression'];
$asks_wellness = false;
foreach ($wellness_keywords as $wk) {
    if (stripos($message, $wk) !== false) {
        $asks_wellness = true;
        break;
    }
}

if ($asks_wellness) {
    $response_text = "I hear you, and it's completely normal to feel this way. Studying at FCAH&PT Ibadan can be challenging. Remember that learning is a marathon, not a sprint. \n\nSince you are feeling stressed, try taking a 15-minute screen-free break. I have set your pacing style to keep things simple for now. Just focus on small steps. 💛";
} elseif ($matched_material) {
    // Format based on VARK style
    $title = $matched_material['title'];
    $raw_content = $matched_material['content_text'];

    if ($vark === 'v') { // Visual (diagrams, structure)
        $response_text = "**Visual Guide: " . $title . "**\n\n";
        $response_text .= "Here is a structural breakdown of the material:\n";
        $response_text .= "```\n";
        $response_text .= "[ " . $title . " ]\n";
        $response_text .= "  | \n";
        $response_text .= "  +--> Key Fact: " . substr($raw_content, 0, 80) . "...\n";
        $response_text .= "  | \n";
        $response_text .= "  +--> Summary Detail: " . substr($raw_content, 80, 80) . "...\n";
        $response_text .= "```\n";
        $response_text .= "Would you like me to draw a text chart for a specific sub-topic?";
    } elseif ($vark === 'a') { // Auditory (verbal explanations, conversational)
        $response_text = "**Let's talk about: " . $title . "**\n\n";
        $response_text .= "Imagine explaining this to a classmate: " . $raw_content . " ";
        $response_text .= "A great way to remember this is to repeat the definitions aloud or try teaching it to someone else. Does this explanation make sense in this format?";
    } elseif ($vark === 'k') { // Kinesthetic (practical, exercises)
        $response_text = "**Practical Exercise: " . $title . "**\n\n";
        $response_text .= "To understand this concept hands-on, try this quick drill:\n\n";
        $response_text .= "1. Write down the core thesis: *" . substr($raw_content, 0, 100) . "...*\n";
        $response_text .= "2. Test yourself: How does this apply to real-world livestock operations or computing systems?\n";
        $response_text .= "3. Implement this case study: " . substr($raw_content, 100, 120) . "...\n\n";
        $response_text .= "Let me know when you finish this first step!";
    } else { // Reading/Writing (notes, bullet points)
        $response_text = "**Lecture Notes: " . $title . "**\n\n";
        $response_text .= "Here are the core points transcribed from the syllabus:\n\n";
        $response_text .= "- **Definition**: " . substr($raw_content, 0, 120) . "\n";
        $response_text .= "- **Key Context**: " . substr($raw_content, 120, 150) . "\n";
        $response_text .= "- **Takeaway Summary**: Review this statement daily to lock it in memory.\n\n";
        $response_text .= "Would you like me to expand on these points?";
    }

    // Apply pace rules
    if ($pace === 'express') {
        // Truncate to keep short
        $response_text = "⚡ **Express Mode Summary**\n\n" . substr($response_text, 0, 200) . "...";
    } elseif ($pace === 'deep') {
        // Append deep info
        $response_text .= "\n\n🔬 *Deep-dive context: The logical progression of this theorem explains the core mechanism. Focus on step-by-step proofs before attempting exams.*";
    }

} else {
    // General fallback
    $response_text = "I couldn't find a direct lecture match for that in your department files. However, based on your **" . strtoupper($vark) . "** profile:\n\n";
    $response_text .= "Try reviewing your course materials or uploading files via your lecturer's portal. In the meantime, I can assist with general study tricks, note-taking, or flashcard creation.";
}

// Adjust wellness instruction
if ($stress === 'high' || $attention === 'low') {
    $response_text .= "\n\n*Take it slow today. You are doing great!*";
}

apiJson([
    'success' => true,
    'message' => $response_text,
    'timestamp' => date('c')
]);
