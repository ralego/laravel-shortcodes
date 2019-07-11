<?php
 
if (!function_exists('num2month')){    
    function num2month($num) 
    {
        $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        return($months[$num - 1]);
    }
}

function nl2p($string)
{
    $paragraphs = '';

    foreach (explode("\n\n", $string) as $line) {
        if (trim($line)) {
            $paragraphs .= '<p>' . $line . '</p>';
        }
    }

    return $paragraphs;
}

function the_meta_content($post){
    $content = $post->content;
    //Borramos comentarios
    $content =  preg_replace('/<!--(.|\s)*?-->/', '', $content);
    //Borramos etiquetas html
    $content = strip_tags($content);  
    //Borramos espacios al final y al inicio
    $content = trim($content);
    //Limitamos el contenido a 170 caracteres
    $content = preg_replace("/(\r?\n){2,}/", " ", $content);
    //Limitamos el contenido a 170 caracteres
    $content = Str::words($content, 25,'');
    return $content;
}

function the_content($post)
{
    $doc = new \DOMDocument;
    @$doc->loadHtml('<?xml encoding="utf-8" ?>' . $post->content);
    
    $body = $doc->getElementsByTagName('body')->item(0);
    $content = $doc->saveHTML($body);

    $content = insert_related_news($post, 'web');
    $content = remplace_url($content);
    $content = twitter_link_replacer($content, 'web');
    $content = facebook_link_replacer($content, 'web');
    $content = instagram_link_replacer($content, 'web');
    $content = youtube_link_replacer($content, 'web');
    $content = nl2p($content);
    $content = safari_reader($content);

    return $content;
}

function the_content_amp($post)
{
    $amp = new \Lullabot\AMP\AMP();
    @$amp->loadHtml($post->content);
    $content = $amp->convertToAmpHtml();

    $content = insert_related_news($post, 'amp');

    $content = remplace_url($content); 
    $content = twitter_link_replacer($content, 'amp');
    $content = facebook_link_replacer($content, 'amp');
    $content = instagram_link_replacer($content, 'amp');
    $content = youtube_link_replacer($content, 'amp');   

    $content = ampify_img($content);

    return $content;
}

function the_content_rss($content)
{
    $content =  preg_replace('/<!--(.|\s)*?-->/', '', $content); 
    $content = remplace_url($content);
    $content = trim($content);

    return $content;
}

function twitter_link_replacer($content, $source)
{
    if (!is_string($content) || false === stripos($content, 'twitter.com')) {
		return $content;
    }
    $pattern = '^http(?:s)?://(?:www.)?twitter\.com\/(\w+)\/status(es)*\/(\d+)\S*[a-zA-Z0-9_]*^';
    if(preg_match_all($pattern, $content, $matches)){
        foreach ($matches[0] as $key) {
            if($source == 'web'){
                try {
                    $client = new \GuzzleHttp\Client();
                    $response = $client->get('https://publish.twitter.com/oembed?url=' . $key);
                    if($response->getStatusCode() == 200){
                        $response_json = json_decode($response->getBody()->getContents(), true);
                        $content = str_replace($key, '<center>'. $response_json['html'] . '</center>', $content);
                    }else{
                        $content = str_replace($key, '', $content);
                    }
                } catch (\Throwable $th) {
                    //return $content;
                }
            }elseif($source == 'amp'){
                if (preg_match('#https?:\/\/twitter\.com(?:\/\#\!\/|\/)(?P<username>[a-zA-Z0-9_]{1,20})\/status(?:es)?\/(?P<tweet>\d+)#i', $key, $matches2)) {
                }
                $content = str_replace($key, '<amp-twitter width="375" height="472"
                layout="responsive" data-tweetid="' . $matches2['tweet'] . '"></amp-twitter>', $content);
            }        
        }
    }
    return $content;
}

function facebook_link_replacer($content, $source)
{
    if (!is_string($content) || false === stripos($content, 'facebook.com')) {
		return $content;
    }    
    $pattern = '^http(?:s)?://(?:www.)?facebook\.com\S*[a-zA-Z0-9_]*^';
    if(preg_match_all($pattern, $content, $matches)){
        foreach ($matches[0] as $key) {         
            if($source == 'web'){
                if (false !== strpos($key, 'video.php' ) || false !== strpos( $key, '/videos/')){
                    $regex = ['#^https?://(www.)?facebook\.com/([^/]+)/videos/([^/]+)?#', '#^https?://(www.)?facebook\.com/video.php\?([^\s]+)#'];
                    foreach ($regex as $r) {
                        preg_match($r, $key, $matches2);
                        if(count($matches2) > 0){
                            //dd($matches2);
                            $content = str_replace($key, '<center><div class="fb-video" data-href="' . $matches2[0] . '" data-width="500" data-show-text="false"></div></center>', $content);
                        }                        
                    }
                    /*preg_match('%<iframe[^>]*+>(?>[^<]*+(?><(?!/iframe>)[^<]*+)*)</iframe>%i',$content, $magches2);*/
                    
                }else{
                    $content = str_replace($key, '<center><div class="fb-post" data-width="500" data-href="' . $key . '"></div></center>', $content);
                }
            }elseif($source == 'amp'){
                $content = str_replace($key, '<amp-facebook width="552" height="310" layout="responsive" data-align-center="true" data-href="' . $key . '"></amp-facebook>', $content);              
            }
        }
    }
    return $content;
}

function instagram_link_replacer($content, $source)
{
    if (!is_string($content) || false === stripos($content, 'instagram.com')) {
		return $content;
    }    
    $pattern = '^http(?:s)?://(?:www.)?instagram\.com\S*[a-zA-Z0-9_]*^';
    if(preg_match_all($pattern, $content, $matches)){
        foreach ($matches[0] as $key) {
            if($source == 'web'){
                $client = new \GuzzleHttp\Client(['http_errors' => false]);
                $response = $client->get('https://api.instagram.com/oembed?url=' . $key);
                if($response->getStatusCode() == 200){
                    $response_json = json_decode($response->getBody()->getContents(), true);
                    $content = str_replace($key, '<center>'. $response_json['html'] . '</center>', $content); 
                }
            }elseif($source == 'amp'){
                if (preg_match('&(*UTF8)instagram.com/p/([^/]+)/?&i', $key, $matches2)) {
                    if (!empty($matches2[1])) {
                        $shortcode = $matches2[1];
                        $content = str_replace($key, '<amp-instagram data-shortcode="' . $shortcode . '" data-captioned width="400" height="400" layout="responsive"></amp-instagram>', $content); 
                    }  
                }          
            }                        
        }
    }
    return $content;
}

function youtube_link_replacer($content, $source)
{
    if ( ! is_string( $content ) || (false === strpos($content, 'youtube.com') && false === strpos($content, 'youtu.be')) ) {
		return $content;
    }
    $pattern = '^http(?:s)?://(?:www.)?(youtube\.com|youtu\.be)\S*[a-zA-Z0-9_]*^';
    if(preg_match_all($pattern, $content, $matches)){
        foreach ($matches[0] as $key) {
            $key = strip_tags($key);
            if (preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $key, $matches2)) {
                if (!empty($matches2[1])) {
                    $shortcode = $matches2[1];
                    if($source == 'web'){
                        $content = str_replace($key, '<span class="embed-youtube" style="text-align:center; display: block;"><iframe class="youtube-player" type="text/html" width="640" height="360" src="https://www.youtube.com/embed/' . $shortcode . '" allowfullscreen="true" style="border:0;"></iframe></span>', $content);
                    }elseif($source == 'amp'){
                        $content = str_replace($key, '<amp-youtube data-videoid="' . $shortcode . '" layout="responsive" width="640" height="360"></amp-youtube>', $content);
                    }
                }
            }
        }
    }
    return $content;
}

function ampify_img ($html) 
{
    preg_match_all("#<img(.*?)\\/?>#", $html, $matches);
    foreach ($matches[1] as $key => $m) {
      preg_match_all('/(alt|src|width|height)=("[^"]*")/i', $m, $matches2);
      $amp_tag = '<amp-img ';
      foreach ($matches2[1] as $key2 => $val) {
        $amp_tag .= $val .'='. $matches2[2][$key2] .' ';
      }
      $amp_tag .= 'layout="responsive"';
      if(strpos($amp_tag, 'width') == false)
        $amp_tag .= ' width="1024"';
      if(strpos($amp_tag, 'height') == false)
        $amp_tag .= ' height="768"';
      $amp_tag .= '>';
      $amp_tag .= '</amp-img>';
      $html = str_replace($matches[0][$key], $amp_tag, $html);
    }
    return $html;
}

function remplace_url($content)
{
    if (!is_string($content) || false === stripos($content, 'seunonoticias.mx')) {
		return $content;
    }    
    $patterns = [
        '^http(?:s)?://(?:www.)?i0.wp.com\/seunonoticias.mx\/wp-content\/uploads\S*[a-zA-Z_]+\'?s*^', 
        '^http(?:s)?://(?:www.)?seunonoticias\.mx\/wp-content\/uploads\S*[a-zA-Z_]+\'?s*^'
    ];
    foreach( $patterns as $p){
        $hosts = ['i0.wp.com','i1.wp.com','i2.wp.com']; 
        if(preg_match_all($p, $content, $matches)){
            foreach ($matches[0] as $key) {
                $indx = array_rand($hosts);
                $parsed = parse_url($key);
                if(in_array ($parsed['host'], $hosts)){
                    $path = str_replace('seunonoticias.mx','wp.seunonoticias.mx',$parsed['path']);
                    $new_url = $parsed['scheme'] . '://' . $parsed['host'] . $path . '?' . $parsed['query'];
                }else{
                    $new_url = $parsed['scheme'] . '://' . $hosts[$indx] . '/wp.seunonoticias.mx'.$parsed['path'].'?fit=600%2C851&ssl=1';
                }
                $content = str_replace($key, $new_url, $content); 
            }    
        }
    }
    return $content;
}

function safari_reader($content)
{
    return str_replace('safari-reader://', 'https://', $content);          
}

function insert_related_news($post, $source) {
    $doc = new \DOMDocument;
    @$doc->loadHtml('<?xml encoding="utf-8" ?>' . $post->content);
    $content = $doc->saveHtml();

    $xp = new \DOMXPath($doc);
    $targetNode = $xp->query('/html/body/p');
    
    if ($targetNode->length) {
        $paragraph = 0;
        $response = getRelatedPosts($post);
        if(count($response->hits) > 0){
            $lis = '';
            foreach ($response->hits as $hit) {                
                $post = App\Models\Post::find($hit->fields->post_id);
                if($post){
                    $link = $source != 'amp' ? $post->permalink : $post->permalink .'amp';
                    $lis = $lis . '<a style="padding-left:20px;" href="' . $link . '"><i class="fa fa-hand-o-right" aria-hidden="true"></i><span> ' . $post->title . '</span></a><br>'; 
                }
            }
            $targetNode = $targetNode->item($paragraph);
            $tpl = new \DOMDocument;
            @$tpl->loadHtml('<?xml encoding="utf-8" ?><div class="related-news"><hr><b>TAMBIÉN TE PUEDE INTERESAR:</b><br>' . $lis . '<hr></div>');
            $targetNode->parentNode->insertBefore($doc->importNode($tpl->documentElement, TRUE), $targetNode->nextSibling);
        }
    }

    $body = $doc->getElementsByTagName('body')->item(0);

    return $doc->saveHTML($body);



    /*$closing_p = '</p>';
    $paragraphs = explode( $closing_p, $content );
    foreach ($paragraphs as $index => $paragraph) {
        if ( trim( $paragraph ) ) {
            $paragraphs[$index] .= $closing_p;
        }
        if ( $paragraph_id == $index + 1 ) {
            $paragraphs[$index] .= $insertion;
        }
    }
    return implode( '', $paragraphs );*/
}

function getRelatedPosts($post){
    $options  = array (
        'http' => 
        array (
          'ignore_errors' => true,
          'method' => 'POST',
          'content' => 
           http_build_query(  array (
              'size' => 3,
            )),
          'header' => 
          array (
            0 => 'Content-Type: application/x-www-form-urlencoded',
          ),
        ),
      );
       
      $context  = stream_context_create( $options );
      $response = file_get_contents(
          'https://public-api.wordpress.com/rest/v1.1/sites/wp.seunonoticias.mx/posts/' . $post->ID . '/related',
          false,
          $context
      );
      return $response = json_decode( $response );
}