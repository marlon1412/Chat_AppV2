<?php 
    session_start();
    if(isset($_SESSION['unique_id'])){
        include_once "config.php";
        $prime = 17;
        $generator = 3;

        // to get the key of user
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
            // Extract the IV (Initialization Vector) from the decoded message
            $iv = substr($decoded_message, 0, 16); // Assuming the IV length is 16 bytes (128 bits for AES)
            // Extract the encrypted message from the decoded message
            $encrypted_message = substr($decoded_message, 16);
            // Decrypt the message using the shared key and IV
            $decrypted_message = openssl_decrypt($encrypted_message, 'AES-128-CBC', $shared_key, OPENSSL_RAW_DATA, $iv);
            return $decrypted_message;
        }

        $outgoing_id = $_SESSION['unique_id']; //number ng sender
        $incoming_id = mysqli_real_escape_string($conn, $_POST['incoming_id']); // galing sa chat app. kukuninn ang sa url
        $output = "";
        
        $sql_outgoing = mysqli_query($conn, "SELECT * FROM users WHERE unique_id = {$outgoing_id} ");
        if(mysqli_num_rows($sql_outgoing) > 0) {
            $row_outgoing = mysqli_fetch_assoc($sql_outgoing);
        } 
        $sql_incoming = mysqli_query($conn, "SELECT * FROM users WHERE unique_id = {$incoming_id}");
        if(mysqli_num_rows($sql_incoming) > 0){
          $row_incoming = mysqli_fetch_assoc($sql_incoming);
        }

        //name
        $sender_outgoing = $row_outgoing['fname'];
        $receiver_incoming = $row_incoming['fname'];
        
        // private key
        $incoming_key = reverse_dh($incoming_id, $generator, $prime);
        $outgoing_key = reverse_dh($outgoing_id, $generator, $prime);

        // combined shared key
        $incoming_SK = bcpowmod($outgoing_id, $incoming_key,$prime);
        $outgoing_SK = bcpowmod($incoming_id, $outgoing_key, $prime);

        if($incoming_SK == $outgoing_SK) {
                $combined_para1 = combined_para($sender_outgoing, $receiver_incoming, $incoming_SK);
                $combined_para2 = combined_para($receiver_incoming,$sender_outgoing, $outgoing_SK);
                $sql = "SELECT * FROM messages LEFT JOIN users ON users.unique_id = messages.outgoing_msg_id
                WHERE (outgoing_msg_id = {$outgoing_id} AND incoming_msg_id = {$incoming_id})
                OR (outgoing_msg_id = {$incoming_id} AND incoming_msg_id = {$outgoing_id}) ORDER BY msg_id";
        $query = mysqli_query($conn, $sql);
        if(mysqli_num_rows($query) > 0){
            while($row = mysqli_fetch_assoc($query)){
                if($row['outgoing_msg_id'] === $outgoing_id){
                    $decrypted_message = decrypt_message($row['msg'], $combined_para1);
                    $output .= '<div class="chat outgoing">
                                <div class="details">
                                    <p>'. $decrypted_message .'</p>
                                </div>
                                </div>';
                } else {
                    $decrypted_message = decrypt_message($row['msg'], $combined_para2);
                    $output .= '<div class="chat incoming">
                                <img src="php/images/'.$row['img'].'" alt="">
                                <div class="details">
                                    <p>'.$decrypted_message.'</p>
                                </div>
                                </div>';
                } 
            }
        }else{
            $output .= '<div class="text">No messages are available. Once you send message they will appear here.</div>';
        }
        echo $output;
                  
        }


        
    }else{
        header("location: ../login.php");
    }

?>