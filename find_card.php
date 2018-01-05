<?php
    /**
     * Parse response from the Deckbrew API into a simpler format that we can
     * transform into a Slack message later on.
     * @param record Card record return from the 3rd party API
     * @param setId If a specific set was requested find the right set returned from the API
     * @return return the created card record
     */
    function parseRecord($record = null, $setId = null) {
        $editions = $record["editions"];
        $card     = array(
                          "name" => $record["name"],
                          "formats" => $record["formats"],
                          "rule_text" => convertTextToEmoji($record["text"]),
                          "cost" => convertTextToEmoji($record["cost"])
                          );
        
        if (!isNull($editions) && count($editions) > 0 && !isNull(reset($editions))) {
            foreach ($editions as $i => $edition) {
                $card['set'] = $edition['set'] . ' (' . $edition['set_id'] . ')';
                $image = $edition['image_url'];
                if (!isNull($setId) && strcasecmp($edition['set_id'], $setId) != 0) {
                    continue;
                }
                if (!isNull($image) && !endsWith($image, "0.jpg")) {
                    $card['image_url'] = $image;
                }
            }
        }
        
        return $card;
    }
    
    /**
     * Basic method to shove the response data into a card array
     * which we can then use to parse the single card data.
     * @param respData Response data received from the 3rd party API
     * @param setId Set ID requested by the user
     * @return Returns array of cards received from the MTG API
     */
    function retrieveCards($respData = null, $setId = null) {
        $cards = array();
        if (isNull($respData) || $respData === '[]') {
            return array();
        }
        $data = json_decode($respData, true);
        if (isNull($data) || count($data) <= 0 || isNull(reset($data))) {
            return array();
        }
        foreach ($data as $item) {
            $card = parseRecord($item, $setId);
            array_push($cards, $card);
        }
        return $cards;
    }
    
    /**
     * Helper method to convert mana symbols into Slack emojis
     * (need to be added to the Slack instance, if not it will only display the strings)
     * @param manaCost mana cost on the card represented in typical MTG text format
     * @return return mana cost as emojis that Slack understands
     */
    function convertTextToEmoji($manaCost = null) {
        if (isNull($manaCost))
            return $manaCost;
        $manaCost = str_replace("{U}", ":blue_mana:", $manaCost);
        $manaCost = str_replace("{R}", ":red_mana:", $manaCost);
        $manaCost = str_replace("{W}", ":white_mana:", $manaCost);
        $manaCost = str_replace("{B}", ":black_mana:", $manaCost);
        $manaCost = str_replace("{G}", ":green_mana:", $manaCost);
        
        $manaCost = str_replace("{T}", ":tap_symbol:", $manaCost);
        $manaCost = str_replace("{X}", ":x_mana:", $manaCost);
        $manaCost = str_replace("{C}", ":colorless_mana:", $manaCost);
        
        $manaCost = str_replace("{0}", ":zero_mana:", $manaCost);
        $manaCost = str_replace("{1}", ":one_mana:", $manaCost);
        $manaCost = str_replace("{2}", ":two_mana:", $manaCost);
        $manaCost = str_replace("{3}", ":three_mana:", $manaCost);
        $manaCost = str_replace("{4}", ":four_mana:", $manaCost);
        $manaCost = str_replace("{5}", ":five_mana:", $manaCost);
        $manaCost = str_replace("{6}", ":six_mana:", $manaCost);
        $manaCost = str_replace("{7}", ":seven_mana:", $manaCost);
        $manaCost = str_replace("{8}", ":eight_mana:", $manaCost);
        $manaCost = str_replace("{9}", ":nine_mana:", $manaCost);
        $manaCost = str_replace("{10}", ":ten_mana:", $manaCost);
        $manaCost = str_replace("{11}", ":eleven_mana:", $manaCost);
        $manaCost = str_replace("{12}", ":twelve_mana:", $manaCost);
        
        $manaCost = str_replace("{B/P}", ":black_phyrexian_mana:", $manaCost);
        $manaCost = str_replace("{R/P}", ":red_phyrexian_mana:", $manaCost);
        $manaCost = str_replace("{U/P}", ":blue_phyrexian_mana:", $manaCost);
        $manaCost = str_replace("{W/P}", ":white_phyrexian_mana:", $manaCost);
        $manaCost = str_replace("{G/P}", ":green_phyrexian_mana:", $manaCost);
        
        $manaCost = str_replace("{W/U}", ":white_blue_mana:", $manaCost);
        $manaCost = str_replace("{W/B}", ":white_black_mana:", $manaCost);
        $manaCost = str_replace("{U/B}", ":blue_black_mana:", $manaCost);
        $manaCost = str_replace("{U/R}", ":blue_red_mana:", $manaCost);
        $manaCost = str_replace("{B/R}", ":black_red_mana:", $manaCost);
        $manaCost = str_replace("{R/W}", ":red_white_mana:", $manaCost);
        $manaCost = str_replace("{R/G}", ":red_green_mana:", $manaCost);
        $manaCost = str_replace("{G/W}", ":green_white_mana:", $manaCost);
        $manaCost = str_replace("{B/G}", ":black_green_mana:", $manaCost);
        $manaCost = str_replace("{G/U}", ":green_blue_mana:", $manaCost);
        return $manaCost;
    }
    
    /**
     * Builds the Slack response if case a card has been found for the input.
     * If no image is available it will generate a text-based response. Also
     * generates emojis for playable formats for the card.
     * @param data Card data generated from the parse API response
     * @return Returns card data as JSON encoded string for Slack
     */
    function generateCardResponse($data) {
        $card     = $data{0};
        $formats  = $card['formats'];
        $sFormats = '';
        foreach ($formats as $i => $format) {
            $status = '=> ' . $format . ',';
            switch ($format) {
                case "legal":
                    $status = ':green_circle:';
                    break;
                case "restricted":
                    $status = ':orange_circle:';
                    break;
                case "banned":
                    $status = ':red_circle:';
                    break;
                    
            }
            $sFormats = $sFormats . $i . ' ' . $status . ' ';
        }
        $sFormats = chop($sFormats, ', ');
        
        $fields = array();
        if (isNull($card['image_url'])) {
            array_push($fields,
                       array('title' => 'Mana cost', 'value' => $card['cost'], 'short' => true),
                       array('title' => 'Set', 'value' => $card['set'], 'short' => true),
                       array('title' => 'Legality', 'value' => $sFormats, 'short' => false),
                       array('title' => 'Rule text', 'value' => $card['rule_text'], 'short' => false)
                       );
        } else {
            array_push($fields, array('title' => 'Legality', 'value' => $sFormats, 'short' => false));
        }
        
        return json_encode(array(
                                 "response_type" => "in_channel",
                                 'attachments' => array(
                                                        array(
                                                              'fields' => $fields,
                                                              "text" => '',
                                                              "image_url" => $card['image_url']
                                                              )
                                                        )
        ));
    }

    /**
     * Generates JSON encoded response for Slack
     * @param data Output data, either card or error info
     * @param foundCards indicator whether cards have been found
     * @return Returns JSON encoded response data for Slack
     */
    function json_response($data = null, $code = 200, $foundCards = false) {
        header_remove();
        http_response_code($code);
        
        header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
        header('Content-Type: application/json');
        $status = array(
                        200 => '200 OK',
                        400 => '400 Bad Request',
                        422 => 'Unprocessable Entity',
                        500 => '500 Internal Server Error'
                        );
        
        header('Status: ' . $status[$code]);
        
        // return the encoded json
        $response = json_encode(array('text' => $data));
        if ($foundCards)
            $response = generateCardResponse($data);
        return $response;
    }
    
    /**
     * Method to process input. Strips input, fetched set info, then pings the
     * Deckbrew API and ultimately returns response to Slack.
     * @param text Input text that holds the card name / set
     * @return Response in JSON format
     */
    function processInput($text) {
        if (isNull($text)) {
            $postRequest = file_get_contents("php://input");
            return json_response('Error, can\'t extract card name from input: ' . $postRequest, 200);
        } else {
            $url = 'https://api.deckbrew.com/mtg/cards/typeahead?q=';
            
            // fetch set id if available
            $setId = null;
            $re = '/<(\w{3})>/';
            preg_match_all($re, $text, $matches, PREG_SET_ORDER, 0);
            if ($matches && count($matches) > 0 && count($matches[0]) > 1) {
                $setId = $matches[0][1];
            }
            
            $cardName = trim(strip_tags($text));
            $cardName = str_replace(" ", "%20", $cardName);
            $cardName = iconv('UTF-8', 'ASCII//TRANSLIT', $cardName);
            
            $ch = curl_init($url . $cardName);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $cards = retrieveCards($response, $setId);
            if (count($cards) > 0)
                return json_response($cards, 200, true);
            else
                return json_response('Error, didnt find any card with the name: ' . $text, 200);
        }
    }
    
    // HELPER METHODS
    function isNull($string) {
        return (!isset($string) || (is_string($string) && trim($string) === ''));
    }
    
    function endsWith($input, $suffix) {
        return $suffix === "" || (($temp = strlen($input) - strlen($suffix)) >= 0 && strpos($input, $suffix, $temp) !== false);
    }
    
    // Executed code when called
    /**
     * Input listener, waits for Slack slash command to ping here and
     * then take action with the card name the user is looking for.
     * Will ultimately echo the result back as response to the call (sync).
     */
    $input = $_POST["text"];
    $response = processInput($input);
    echo $response;
?>
