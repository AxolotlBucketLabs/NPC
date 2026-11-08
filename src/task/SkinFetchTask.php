<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\task;

use AxolotlBucketLabs\NPC\Main;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use function array_map;
use function filter_var;
use function gethostbynamel;
use function imagecolorat;
use function imagecolorsforindex;
use function imagecreatefromstring;
use function imagedestroy;
use function imagesx;
use function imagesy;
use function intval;
use function is_array;
use function is_string;
use function parse_url;
use function preg_match;
use function round;
use function strlen;
use function strtolower;
use function str_replace;
use function trim;
use const FILTER_FLAG_NO_PRIV_RANGE;
use const FILTER_FLAG_NO_RES_RANGE;
use const FILTER_VALIDATE_IP;

/** Downloads and normalizes a direct PNG/RGBA skin off the server thread. */
final class SkinFetchTask extends AsyncTask{
	/** @param list<string> $allowedHosts */
	public function __construct(
		private string $pluginName,
		private string $npcId,
		private string $url,
		private array $allowedHosts,
		private int $maxBytes,
		private int $timeout
	){ }

	public function onRun() : void{
		$parts = parse_url($this->url);
		$host = is_array($parts) && is_string($parts["host"] ?? null) ? strtolower($parts["host"]) : "";
		$port = is_array($parts) && isset($parts["port"]) ? $parts["port"] : 443;
		if($host === "" || $port !== 443 || !in_array($host, $this->allowedHosts, true) || !$this->isPublicHost($host)){
			$this->setResult(["ok" => false, "error" => "host is not allowed"]);
			return;
		}

		$handle = curl_init($this->url);
		if($handle === false){
			$this->setResult(["ok" => false, "error" => "unable to initialize HTTPS client"]);
			return;
		}
		$body = "";
		curl_setopt_array($handle, [
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => $this->timeout,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_USERAGENT => "NPC/skin-fetch",
			CURLOPT_WRITEFUNCTION => static function(mixed $handle, string $chunk) use (&$body) : int{
				if(strlen($body) + strlen($chunk) > 8 * 1024 * 1024){
					return 0;
				}
				$body .= $chunk;
				return strlen($chunk);
			},
			CURLOPT_HTTPHEADER => ["Accept: image/png,image/*;q=0.8,application/octet-stream;q=0.5"]
		]);
		curl_exec($handle);
		$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		curl_close($handle);
		if($code < 200 || $code >= 300 || strlen($body) > $this->maxBytes){
			$this->setResult(["ok" => false, "error" => "skin download failed or exceeded size limit"]);
			return;
		}

		$rgba = $this->normalizeImage($body);
		if($rgba === null){
			$this->setResult(["ok" => false, "error" => "skin is not a supported 64x32, 64x64 or 128x128 image"]);
			return;
		}
		$this->setResult(["ok" => true, "data" => $rgba]);
	}

	public function onCompletion() : void{
		$plugin = Server::getInstance()->getPluginManager()->getPlugin($this->pluginName);
		if($plugin instanceof Main){
			$result = $this->getResult();
			if(is_array($result)){
				/** @var array<string, mixed> $result */
				$plugin->completeSkinFetch($this->npcId, $result);
			}else{
				$plugin->completeSkinFetch($this->npcId, ["ok" => false, "error" => "empty skin result"]);
			}
		}
	}

	private function isPublicHost(string $host) : bool{
		$addresses = gethostbynamel($host);
		if(!is_array($addresses) || $addresses === []){
			return false;
		}
		foreach($addresses as $address){
			if(filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false){
				return false;
			}
		}
		return true;
	}

	private function normalizeImage(string $body) : ?string{
		if(strlen($body) === 64 * 32 * 4 || strlen($body) === 64 * 64 * 4 || strlen($body) === 128 * 128 * 4){
			return $body;
		}
		if(!function_exists("imagecreatefromstring")){
			return null;
		}
		$image = @imagecreatefromstring($body);
		if($image === false){
			return null;
		}
		$width = imagesx($image);
		$height = imagesy($image);
		if(!in_array([$width, $height], [[64, 32], [64, 64], [128, 128]], true)){
			imagedestroy($image);
			return null;
		}
		$rgba = "";
		for($y = 0; $y < $height; ++$y){
			for($x = 0; $x < $width; ++$x){
				$pixel = imagecolorat($image, $x, $y);
				if($pixel === false){
					imagedestroy($image);
					return null;
				}
				$color = imagecolorsforindex($image, $pixel);
				$rgba .= chr($color["red"]) . chr($color["green"]) . chr($color["blue"]) . chr(255 - (int) round($color["alpha"] * 255 / 127));
			}
		}
		imagedestroy($image);
		return $rgba;
	}
}
