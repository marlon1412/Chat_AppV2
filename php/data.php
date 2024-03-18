<?php
$prime = 17;
$generator = 3;

function reverse_dh($combined_id, $generator, $prime) {
    for ($key = 1; $key <= $prime; $key++) {
        $computed_key = bcpowmod($generator, $combined_id, $prime);
        if ($computed_key == $combined_id) {
            return $key;
        }
    }
    return null;
}

function combined_para($sender_name, $receiver_name, $key) {
    // Concatenate the sender name, receiver name, and key
    $shared_key = $sender_name . $receiver_name . $key;
    // Truncate or pad the shared key to a fixed length (e.g., 16 bytes for AES-128)
    $shared_key = substr($shared_key, 0, 16); // Adjust the length as needed for your encryption algorithm
    return $shared_key;
}

function decrypt_message($encrypted_message, $shared_key) {
    // Decode the base64 encoded encrypted message
    $decoded_message = base64_decode($encrypted_message);
    // Determine the IV length based on the cipher algorithm
    $iv_length = openssl_cipher_iv_length('AES-128-CBC');
    // Extract the IV (Initialization Vector) from the decoded message
    $iv = substr($decoded_message, 0, $iv_length);
    // Extract the encrypted message from the decoded message
    $encrypted_message = substr($decoded_message, $iv_length);
    // Decrypt the message using the shared key and IV
    $decrypted_message = openssl_decrypt($encrypted_message, 'AES-128-CBC', $shared_key, OPENSSL_RAW_DATA, $iv);
    return $decrypted_message;
}

while ($row = mysqli_fetch_assoc($query)) {
    $sql2 = "SELECT * FROM messages WHERE (incoming_msg_id = {$row['unique_id']}
            OR outgoing_msg_id = {$row['unique_id']}) AND (outgoing_msg_id = {$outgoing_id} 
            OR incoming_msg_id = {$outgoing_id}) ORDER BY msg_id DESC LIMIT 1";

    $outgoing_id = mysqli_real_escape_string($conn, $outgoing_id);
    $incoming_id = mysqli_real_escape_string($conn, $row['unique_id']);

    $sql_outgoing = mysqli_query($conn, "SELECT * FROM users WHERE unique_id = {$outgoing_id} ");
    if (mysqli_num_rows($sql_outgoing) > 0) {
        $row_outgoing = mysqli_fetch_assoc($sql_outgoing);
    } 
    $sql_incoming = mysqli_query($conn, "SELECT * FROM users WHERE unique_id = {$incoming_id}");
    if (mysqli_num_rows($sql_incoming) > 0) {
        $row_incoming = mysqli_fetch_assoc($sql_incoming);
    }

    $sender_outgoing = isset($row_outgoing['fname']) ? $row_outgoing['fname'] : "";
    $receiver_incoming = isset($row_incoming['fname']) ? $row_incoming['fname'] : "";

    $incoming_key = reverse_dh($incoming_id, $generator, $prime);
    $outgoing_key = reverse_dh($outgoing_id, $generator, $prime);

    $incoming_SK = bcpowmod($outgoing_id, $incoming_key, $prime);
    $outgoing_SK = bcpowmod($incoming_id, $outgoing_key, $prime);

    $combined_para1 = combined_para($sender_outgoing, $receiver_incoming, $incoming_SK);
    $combined_para2 = combined_para($receiver_incoming, $sender_outgoing, $outgoing_SK);

    $query2 = mysqli_query($conn, $sql2);
    $row2 = mysqli_fetch_assoc($query2);
    $decrypted_message = "";

    if (mysqli_num_rows($query2) > 0) {
        $result = $row2['msg'];
        if ($outgoing_id == $row2['outgoing_msg_id']) {
            $decrypted_message = decrypt_message($result, $combined_para1);
        } else {
            $decrypted_message = decrypt_message($result, $combined_para2);
        }
    } else {
        $decrypted_message = "No available message";
    }

    $msg = (strlen($decrypted_message) > 28) ? substr($decrypted_message, 0, 28) . '...' : $decrypted_message;

    $you = (isset($row2['outgoing_msg_id']) && $outgoing_id == $row2['outgoing_msg_id']) ? "You: " : "";
    $offline = ($row['status'] == "Offline now") ? "offline" : "";
    $hid_me = ($outgoing_id == $row['unique_id']) ? "hide" : "";

    $output .= '<a href="chat.php?user_id='. $row['unique_id'] .'">
                <div class="content">
                <img src="php/images/'. $row['img'] .'" alt="">
                <div class="details">
                    <span>'. $row['fname']. " " . $row['lname'] .'</span>
                    <p>'. $you . $msg .'</p>
                </div>
                </div>
                <div class="status-dot '. $offline .'"><i class="fas fa-circle"></i></div>
            </a>';
}
    
// 

?>



