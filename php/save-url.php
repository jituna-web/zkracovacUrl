<?php
    include "config.php";
    $og_url = mysqli_real_escape_string($conn, $_POST['shorten_url']);
    $shorten_url = str_replace(' ', '', $og_url);
    $hidden_url = mysqli_real_escape_string($conn, $_POST['hidden_url']);

    if(!empty($shorten_url)){
        if(preg_match("/\//i", $shorten_url)){
            $explodeURL = explode('/', $shorten_url);
            $shortURL = end($explodeURL);
            if($shortURL != ""){
                $sql = mysqli_query($conn, "SELECT shorten_url FROM url WHERE shorten_url = '{$shortURL}' && shorten_url != '{$hidden_url}'");
                if(mysqli_num_rows($sql) == 0){
                    $sql2 = mysqli_query($conn, "UPDATE url SET shorten_url = '{$shortURL}' WHERE shorten_url = '{$hidden_url}'");
                    if($sql2){
                        echo "success";
                    }else{
                        echo "Chyba – nepodařilo se aktualizovat odkaz!";
                    }
                }else{
                    echo "Zkrácená adresa URL, kterou jste zadali, již existuje. Zadejte prosím jiný!";
                }
            }else{
                echo "Povinné – musíte zadat zkrácenou adresu URL!";
            }
        }else{
            echo "Neplatná adresa URL – název domény nelze upravit!";
        }
    }else{
        echo "Chyba – musíte zadat zkrácenou adresu URL!";
    }
?>