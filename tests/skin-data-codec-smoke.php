<?php

declare(strict_types=1);

if($argc < 2){
	fwrite(STDERR, "Usage: php skin-data-codec-smoke.php <Axolotl-PM root>\n");
	exit(2);
}

$serverRoot = realpath($argv[1]);
if($serverRoot === false || !is_file($serverRoot . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php")){
	fwrite(STDERR, "Axolotl-PM root does not contain vendor/autoload.php\n");
	exit(2);
}

require $serverRoot . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\color\Color;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\skin\SkinArmSizeType;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;

$skinImage = new SkinImage(32, 64, str_repeat("\x11\x22\x33\xff", 32 * 64));
$original = new SkinData(
	"persona-test",
	"{\"geometry\":{\"default\":\"geometry.humanoid.custom\"}}",
	$skinImage,
	[],
	null,
	"{\"geometry.humanoid.custom\":{}}",
	"1.26.60",
	"animation-data",
	"cape-id",
	"full-persona-id",
	SkinArmSizeType::SLIM,
	new Color(1, 2, 3, 255),
	[],
	[],
	SkinData::TRUSTED_SKIN_TRUE,
	true,
	true,
	true,
	false,
	true,
	"profile-hash"
);

$writer = new ByteBufferWriter("");
CommonTypes::putSkin($writer, $original);
$reader = new ByteBufferReader($writer->getData());
$decoded = CommonTypes::getSkin($reader);

if($reader->getUnreadLength() !== 0 || !$decoded->isPersona() || !$decoded->isPersonaCapeOnClassic() || $decoded->getSkinId() !== "persona-test" || $decoded->getSkinImage()->getData() !== $skinImage->getData() || $decoded->getGeometryDataJson() !== "{\"geometry.humanoid.custom\":{}}" || $decoded->getFullSkinId() !== "full-persona-id" || $decoded->getProfileHash() !== "profile-hash"){
	throw new RuntimeException("SkinData persona snapshot round trip failed");
}

echo "SkinData codec smoke: PASS\n";
