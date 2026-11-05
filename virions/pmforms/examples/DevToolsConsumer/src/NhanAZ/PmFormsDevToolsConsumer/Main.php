<?php

declare(strict_types=1);

namespace NhanAZ\PmFormsDevToolsConsumer;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\CustomFormResponse;
use dktapps\pmforms\FormIcon;
use dktapps\pmforms\MenuForm;
use dktapps\pmforms\MenuOption;
use dktapps\pmforms\ModalForm;
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Label;
use dktapps\pmforms\element\Slider;
use dktapps\pmforms\element\StepSlider;
use dktapps\pmforms\element\Toggle;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class Main extends PluginBase{
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if($command->getName() !== "form"){
			return false;
		}
		if($args === [] || !in_array($args[0], ["modal", "menu", "custom"], true)){
			return false;
		}

		$players = $this->resolvePlayers($sender, array_slice($args, 1));
		if($players === null){
			return true;
		}

		switch($args[0]){
			case "modal":
				$form = $this->createModalForm();
				break;
			case "menu":
				$form = $this->createMenuForm();
				break;
			case "custom":
				$form = $this->createCustomForm();
				break;
			default:
				return false;
		}

		foreach($players as $player){
			$player->sendForm($form);
		}
		return true;
	}

	/** @param string[] $playerNames
	 *  @return Player[]|null
	 */
	private function resolvePlayers(CommandSender $sender, array $playerNames) : ?array{
		$players = [];
		foreach($playerNames as $playerName){
			$player = $this->getServer()->getPlayerByPrefix($playerName);
			if($player === null){
				$sender->sendMessage(TextFormat::RED . "No online player matches \"$playerName\".");
				return null;
			}
			$players[] = $player;
		}

		if($players === []){
			if(!$sender instanceof Player){
				$sender->sendMessage(TextFormat::RED . "Specify at least one player when running this command from console.");
				return null;
			}
			$players[] = $sender;
		}

		return $players;
	}

	private function createModalForm() : ModalForm{
		return new ModalForm(
			"Example Modal Form",
			"Do you want to continue?",
			function(Player $player, bool $choice) : void{
				$answer = $choice ? "YES" : "NO";
				$this->getServer()->broadcastMessage(TextFormat::AQUA . $player->getName() . " chose " . $answer . ".");
			},
			"YES",
			"NO",
			function(Player $player) : void{
				$player->sendMessage(TextFormat::YELLOW . "You closed the modal form.");
			}
		);
	}

	private function createMenuForm() : MenuForm{
		return new MenuForm(
			"Example Menu Form",
			"Choose one of the options below.",
			[
				new MenuOption("Option 1"),
				new MenuOption("Option 2", new FormIcon("https://avatars.githubusercontent.com/u/45735136?v=4")),
				new MenuOption("Option 3")
			],
			function(Player $player, int $selectedOption) : void{
				$this->getServer()->broadcastMessage(TextFormat::LIGHT_PURPLE . $player->getName() . " selected menu option " . ($selectedOption + 1) . ".");
			},
			function(Player $player) : void{
				$player->sendMessage(TextFormat::YELLOW . "You closed the menu form.");
			}
		);
	}

	private function createCustomForm() : CustomForm{
		return new CustomForm(
			"Example Custom Form",
			[
				new Label("intro", "This form demonstrates the available input elements."),
				new Dropdown("dropdown", "Choose an option", ["One", "Two", "Three"]),
				new Input("name", "Your name", "Type a name here"),
				new Slider("slider", "Choose a number", 0.0, 10.0, 0.5, 5.0),
				new StepSlider("step_slider", "Choose a preset", ["Small", "Medium", "Large"], 1),
				new Toggle("enabled", "Enable the example", true)
			],
			function(Player $player, CustomFormResponse $response) : void{
				$player->sendMessage(TextFormat::GREEN . "Form submitted:");
				$player->sendMessage("Name: " . $response->getString("name"));
				$player->sendMessage("Dropdown index: " . $response->getInt("dropdown"));
				$player->sendMessage("Slider value: " . $response->getFloat("slider"));
				$player->sendMessage("Preset index: " . $response->getInt("step_slider"));
				$player->sendMessage("Enabled: " . ($response->getBool("enabled") ? "yes" : "no"));
			},
			function(Player $player) : void{
				$player->sendMessage(TextFormat::YELLOW . "You closed the custom form.");
			}
		);
	}
}
