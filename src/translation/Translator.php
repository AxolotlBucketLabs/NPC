<?php

declare(strict_types=1);

namespace AxolotlBucketLabs\NPC\translation;

use AxolotlBucketLabs\NPC\Main;
use pocketmine\utils\Config;
use function array_replace;
use function in_array;
use function is_string;
use function strtolower;
use function str_replace;

final class Translator{
	/** @var list<string> */
	public const SUPPORTED_LANGUAGES = [
		"bul", "chs", "deu", "eng", "fra", "ita", "jpn", "kor",
		"lav", "nld", "rus", "spa", "tur", "vie"
	];

	/** @var array<string, string> */
	private array $messages;

	/** @var array<string, string> */
	private array $englishMessages;

	private string $language;

	public function __construct(Main $plugin){
		foreach(self::SUPPORTED_LANGUAGES as $language){
			$plugin->saveResource("lang/messages-" . $language . ".yml");
		}

		$configLanguageValue = $plugin->getConfig()->getNested("settings.language", "eng");
		$configLanguage = is_string($configLanguageValue) ? strtolower($configLanguageValue) : "eng";
		$this->language = in_array($configLanguage, self::SUPPORTED_LANGUAGES, true) ? $configLanguage : "eng";
		$this->englishMessages = $this->load($plugin, "eng");
		$this->messages = $this->language === "eng" ? $this->englishMessages : array_replace($this->englishMessages, $this->load($plugin, $this->language));
	}

	public function getLanguage() : string{
		return $this->language;
	}

	/** @param array<string, scalar> $parameters */
	public function translate(string $key, array $parameters = []) : string{
		$message = $this->messages[$key] ?? $this->englishMessages[$key] ?? $key;
		foreach($parameters as $name => $value){
			$message = str_replace("{" . $name . "}", (string) $value, $message);
		}
		return $message;
	}

	/** @return array<string, string> */
	private function load(Main $plugin, string $language) : array{
		$config = new Config($plugin->getDataFolder() . "lang/messages-" . $language . ".yml", Config::YAML);
		$messages = [];
		foreach($config->getAll() as $key => $value){
			if(is_string($key) && is_string($value)){
				$messages[$key] = $value;
			}
		}
		return $messages;
	}
}
