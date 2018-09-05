<?php

function getUrl( $params ) {

	$encoded_params = array();

	foreach ( $params as $k => $v ) {
		$encoded_params[] = urlencode( $k ) . '=' . urlencode( $v );
	}

	return "https://api.flickr.com/services/rest/?" . implode( '&', $encoded_params );
}

function removeApiKey( $url ) {
	$api_key = explode( "api_key=", $url )[1];
	$api_key = explode( "&", $api_key )[0];
	$url     = str_replace( "api_key=" . $api_key . "&", "", $url );
	$url     = str_replace( "php_serial", "json", $url );

	return $url;
}

function getArray( $url ) {

	$rsp = file_get_contents( $url );

	$rsp_obj = unserialize( $rsp );

	return $rsp_obj;
}

#
#呼び出すAPI URLの作成
#

$ini_array = parse_ini_file("config.ini");

$api_key = $ini_array["api_key"];

//既存のURL
$manifest_uri = ( empty( $_SERVER["HTTPS"] ) ? "http://" : "https://" ) . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

preg_match( '|' . dirname( $_SERVER['SCRIPT_NAME'] ) . '/([\w%/]*)|', $_SERVER['REQUEST_URI'], $matches );
$paths = explode( '/', $matches[1] );

if ( count( $paths ) == 3 ) {
	$user_id = isset( $paths[0] ) ? htmlspecialchars( $paths[0] ) : null;
	//特殊文字を置換する
	$user_id     = urldecode( $user_id );
	$photoset_id = isset( $paths[2] ) ? htmlspecialchars( $paths[2] ) : null;
} else if ( count( $paths ) == 1 && $paths[0] != "" ) {//@を含む場合には新しいurlをお知らせする

	//特殊文字を置換したURL
	$url = str_replace( "@", urlencode( "@" ), $manifest_uri );
	print( "<p>Please try the following URL:</p>" );
	print( "<a href='" . $url . "'>" . $url . "</a>" );

	return null;
} else {
	$user_id     = '154092236@N05';
	$photoset_id = '72157693228072994';
}

//@を含まない場合には、User_idを取得する
if ( strpos( $user_id, '@' ) == false ) {
	$params = array(
		'api_key' => $api_key,
		'method'  => 'flickr.urls.lookupUser',
		'format'  => 'php_serial',
		'url'     => "https://www.flickr.com/photos/" . $user_id,
	);

	$rsp_obj_url = getUrl( $params );

	$rsp_obj = getArray( $rsp_obj_url );

	$user_id = $rsp_obj["user"]["id"];
}

$params = array(
	'api_key'     => $api_key,
	'method'      => 'flickr.photosets.getPhotos',
	'format'      => 'php_serial',
	'photoset_id' => $photoset_id,        // 検索ワードの指定
	'user_id'     => $user_id,            // 取得件数
);

$rsp_obj_url = getUrl( $params );
$rsp_obj     = getArray( $rsp_obj_url );

$photo_array = $rsp_obj["photoset"]["photo"];

$canvases = [];

for ( $i = 0; $i < count( $photo_array ); $i ++ ) {
	$photo = $photo_array[ $i ];

	$params = array(
		'api_key'  => $api_key,
		'method'   => 'flickr.photos.getSizes',
		'format'   => 'php_serial',
		'photo_id' => $photo["id"],
	);

	$photo_url = getUrl( $params );
	$size      = getArray( $photo_url )["sizes"]["size"];

	$original = null;

	for ( $j = count( $size ) - 1; $j >= 0; $j -- ) {
		if ( strpos( $size[ $j ]["label"], 'Large' ) !== false ) {
			$original = $size[ $j ];
			break;
		}
	}

	$p         = $i + 1;
	$canvas_id = removeApiKey( $photo_url );
	$width     = (int) $original["width"];
	$height    = (int) $original["height"];

	$resource           = [];
	$resource["@id"]    = $original["source"];
	$resource["@type"]  = "dctypes:Image";
	$resource["format"] = "image/jpeg";
	$resource["width"]  = $width;
	$resource["height"] = $height;

	$images              = [];
	$image["resource"]   = $resource;
	$image["@id"]        = $original["url"];
	$image["@type"]      = "oa:Annotation";
	$image["motivation"] = "sc:painting";
	$image["on"]         = $canvas_id;

	$images[] = $image;

	$canvas        = [];
	$canvas["@id"] = $canvas_id;

	$thumbnail        = [];
	$thumbnail["@id"] = $size[2]["source"];

	$canvas["@type"]     = "sc:Canvas";
	$canvas["label"]     = "[" . $p . "]";
	$canvas["thumbnail"] = $thumbnail;
	$canvas["width"]     = $width;
	$canvas["height"]    = $height;
	$canvas["images"]    = $images;

	$canvases[] = $canvas;
}

$params = array(
	'api_key'     => $api_key,
	'method'      => 'flickr.photosets.getInfo',
	'format'      => 'php_serial',
	'photoset_id' => $photoset_id,
);

$info_url = getUrl( $params );
$info     = getArray( $info_url )["photoset"];

$data = [];

$data["@context"] = "http://iiif.io/api/presentation/2/context.json";
$data["@id"]      = $manifest_uri;
$data["@type"]    = "sc:Manifest";


$data["label"]       = $info["title"]["_content"];
$data["attribution"] = $info["username"];
$data["description"] = $info["description"]["_content"];

$sequences = [];

$sequence["@id"]         = removeApiKey( $rsp_obj_url );
$sequence["@type"]       = "sc:Sequence";
$sequence["label"]       = "Current Page Order";
$sequence["viewingHist"] = "non-paged";
$sequence["canvases"]    = $canvases;

$sequences[] = $sequence;

$data["sequences"] = $sequences;

# Content-Typeを「application/json」に設定します。
header( "Content-Type: application/json; charset=UTF-8" );
# IEがContent-Typeヘッダーを無視しないようにします(HTML以外のものをHTML扱いしてしまうことを防ぐため)
header( "X-Content-Type-Options: nosniff" );
# 可能な限りのエスケープを行い、JSON形式で結果を返します。


echo json_encode(
	$data,
	JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
