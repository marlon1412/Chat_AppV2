<?php 
    session_start();
    if(isset($_SESSION['unique_id'])){
        include_once "config.php";
        $encrypted_mess = "";
        $prime = 17;
        $generator = 3;


        function combined_para($sender_name, $receiver_name, $key) {
            // Concatenate the sender name, receiver name, and key
            $shared_key = $sender_name . $receiver_name . $key;
            // Truncate or pad the shared key to a fixed length (e.g., 16 bytes for AES-128)
            $shared_key = substr($shared_key, 0, 16); // Adjust the length as needed for your encryption algorithm
            return $shared_key;
        }
        
        // Function to encrypt the message using the shared key (AES encryption for example)
        function encrypt_message($message, $shared_key) {
            // Use a suitable encryption algorithm, such as AES
            // This example uses AES encryption with CBC mode and PKCS7 padding
            $iv = openssl_random_pseudo_bytes(16); // Generate a random IV (Initialization Vector)
            $encrypted_message = openssl_encrypt($message, 'AES-128-CBC', $shared_key, OPENSSL_RAW_DATA, $iv);
            // Combine the IV and the encrypted message
            $result = base64_encode($iv . $encrypted_message);
            return $result;
        }

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

        $outgoing_id = $_SESSION['unique_id']; // users id
        $incoming_id = mysqli_real_escape_string($conn, $_POST['incoming_id']); // sesendan ng message id
        $message = mysqli_real_escape_string($conn, $_POST['message']); // escaping special charater to ensure safe insertion
        
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
            if(!empty($message)){
                $combined_para = combined_para($sender_outgoing, $receiver_incoming, $incoming_SK);
                // Encrypt the message using the shared key
                $encrypted_message = encrypt_message($message, $combined_para);
                $sql = mysqli_query($conn, "INSERT INTO messages (incoming_msg_id, outgoing_msg_id, msg)
                                    VALUES ({$incoming_id}, {$outgoing_id}, '{$encrypted_message}')") or die();    
        }
        } else {
            header("location: ../login.php");
        }

    }else{
        header("location: ../login.php");
    }


    
?>