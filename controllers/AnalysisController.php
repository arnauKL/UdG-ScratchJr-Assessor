<?php

class AnalysisController
{
    // These are "Class Properties". Static means they belong to the class, not a specific object.
    static $view = "analysis";
    static $style = "analysis";
    static $path = ROOT . "/assets/temp/rubric/";

    static $svgSpriteHashes;

    public function main()
    {
        $json = [];
        $style = self::$style;
        if (isset($_FILES)) {
            if (!is_dir(self::$path)) {
                mkdir(self::$path, 0777, true);
            }
            switch (sizeof($_FILES)) {
                case 1:
                    $view = self::$view . "_solo";
                    if (
                        (isset($_SESSION["content"]) &&
                            $_SESSION["content"] == null) ||
                        !isset($_SESSION["content"])
                    ) {
                        $_SESSION["content"] = $this->checkSolo(
                            $_FILES["soloFile"],
                        );
                    }
                    $json[] = $_SESSION["content"];
                    echo "<script>let data =" .
                        json_encode($json[0]) .
                        "</script>";
                    require_once ROOT . "/views/layout/skeleton.php";
                    return;
                case 2:
                    $view = self::$view . "_duo";
                    if (
                        (isset($_SESSION["content"]) &&
                            $_SESSION["content"] == null) ||
                        !isset($_SESSION["content"])
                    ) {
                        $_SESSION["content"] = $this->checkDuo(
                            $_FILES["duoFileOne"],
                            $_FILES["duoFileTwo"],
                        );
                    }
                    $json = $_SESSION["content"];
                    echo "<script>
                        let dataA =" .
                        json_encode($json[0]) .
                        "
                        let dataB =" .
                        json_encode($json[1]) .
                        "
                        </script>";
                    require_once ROOT . "/views/layout/skeleton.php";
                    return;
                default:
                    return;
            }
        }
    }

    private function checkSolo($file)
    {
        $zip = new ZipArchive();
        $json = null;

        if (isset($file)) {
            $destinationFile =
                explode(".", $file["tmp_name"])[0] . "-" . $file["name"];
            $destinationPath = self::$path . basename($destinationFile);

            if (move_uploaded_file($file["tmp_name"], $destinationPath)) {
                $extractPath = explode(".", $destinationPath)[0];
                if ($zip->open($destinationPath) === true) {
                    if (!is_dir($extractPath)) {
                        mkdir($extractPath, 0777, true);
                    }
                    $zip->extractTo($extractPath);
                    $zip->close();

                    $json["content"] = json_decode(
                        file_get_contents($extractPath . "/project/data.json"),
                        true,
                    );
                    $json["scoring"] = self::calculateRubricScore(
                        $json["content"],
                        $extractPath,
                    );
                }

                $json["extra"]["thumbnailFormat"] = mime_content_type(
                    $extractPath .
                        "/project/thumbnails/" .
                        $json["content"]["thumbnail"]["md5"],
                );
                $json["extra"]["thumbnailEncoded"] = base64_encode(
                    file_get_contents(
                        $extractPath .
                            "/project/thumbnails/" .
                            $json["content"]["thumbnail"]["md5"],
                    ),
                );
                $json["extra"]["pageImages"] = [];

                $spriteResult = self::getAllSpritesInProject(
                    $json["content"]["json"],
                );
                $json["extra"]["characterCount"] = array_sum(
                    array_map(function ($value) {
                        return sizeof($value);
                    }, $spriteResult["pages"]),
                );
                $json["extra"]["characterImages"] = [];

                foreach ($json["content"]["json"]["pages"] as $page) {
                    $pagePath = isset($json["content"]["json"][$page]["md5"])
                        ? $extractPath .
                            "/project/backgrounds/" .
                            $json["content"]["json"][$page]["md5"]
                        : ROOT .
                            "/assets/temp/private/backgrounds/DefaultWhite.svg";
                    $json["extra"]["pageImages"][$page] = base64_encode(
                        file_get_contents($pagePath),
                    );
                    $json["extra"]["characterImages"][$page] = [];
                    foreach (
                        $json["content"]["json"][$page]["sprites"]
                        as $sprite
                    ) {
                        if (
                            $json["content"]["json"][$page][$sprite]["type"] ===
                            "sprite"
                        ) {
                            $spritePath = file_exists(
                                $extractPath .
                                    "/project/characters/" .
                                    $json["content"]["json"][$page][$sprite][
                                        "md5"
                                    ],
                            )
                                ? $extractPath .
                                    "/project/characters/" .
                                    $json["content"]["json"][$page][$sprite][
                                        "md5"
                                    ]
                                : ROOT .
                                    "/assets/temp/private/sprites/" .
                                    $json["content"]["json"][$page][$sprite][
                                        "md5"
                                    ];
                            $json["extra"]["characterImages"][$page][
                                $sprite
                            ] = base64_encode(file_get_contents($spritePath));
                        } elseif (
                            $json["content"]["json"][$page][$sprite]["type"] ===
                            "text"
                        ) {
                            $text =
                                $json["content"]["json"][$page][$sprite]["str"];
                            $color =
                                $json["content"]["json"][$page][$sprite][
                                    "color"
                                ];
                            $json["extra"]["characterImages"][$page][
                                $sprite
                            ] = base64_encode(
                                '<svg height="200" width="200" xmlns="http://www.w3.org/2000/svg">
                            <text text-anchor="middle" x="100" y="100" fill="' .
                                    $color .
                                    '" stroke="' .
                                    $color .
                                    '" font-size="20">' .
                                    htmlspecialchars($text) .
                                    '</text>
                            </svg>',
                            );
                        }
                    }
                }
                unlink($destinationPath);
                self::rrmdir($extractPath);

                return $json;
            }
        }
    }

    private function checkDuo($fileOne, $fileTwo)
    {
        $outOne = self::checkSolo($fileOne);
        $outTwo = self::checkSolo($fileTwo);
        return [$outOne, $outTwo];
    }

    private static function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        self::rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    private static function calculateRubricScore($entry, $extractPath)
    {
        $out = [];
        $out[
            "programSyntaxLengthAmount"
        ] = self::calculateProgramSyntaxLengthAmount($entry);
        $out["varietyOfBlocks"] = self::calculateVarietyOfBlocks($entry);
        $out["coordination"] = self::calculateCoordination($entry);
        $out["repeatNumberParameters"] = self::calculateRepeatNumberParameters(
            $entry,
        );
        $out["narrativeCohesion"] = ["categoryScore" => null];
        $out["visualCustomization"] = self::calculateVisualCustomization(
            $entry,
            $extractPath,
        );
        $out[
            "programmedCustomization"
        ] = self::calculateProgrammedCustomization($entry);

        $tempFinalScore = array_sum(
            array_map(function ($value) {
                return $value["categoryScore"] ?? 0;
            }, $out),
        );

        $out["finalScore"] = $tempFinalScore;
        return $out;
    }

    private static function getAllBlocksOnSprite($sprite)
    {
        $out = [];
        if (isset($sprite["scripts"])) {
            foreach ($sprite["scripts"] as $script) {
                foreach ($script as $block) {
                    $out[] = $block;
                }
            }
        }
        return $out;
    }

    private static function getAllBlocksOnPage($page)
    {
        $out = [];
        foreach ($page["sprites"] as $sprite) {
            //In each sprite
            foreach (self::getAllBlocksOnSprite($page[$sprite]) as $block) {
                $out[] = $block;
            }
        }
        return $out;
    }

    private static function getAllBlocksInProject($json)
    {
        $out = [];
        foreach ($json["pages"] as $page) {
            foreach (self::getAllBlocksOnPage($json[$page]) as $block) {
                $out[] = $block;
            }
        }
        return $out;
    }

    private static function getAllSpritesInProject($json)
    {
        $out = [];
        $out["pages"] = [];
        foreach ($json["pages"] as $page) {
            $out["pages"][$page] = [];
            foreach ($json[$page]["sprites"] as $sprite) {
                $out["pages"][$page][] =
                    $json[$page][$sprite]["md5"] ??
                    ($json[$page][$sprite]["text"] ?? null);
            }
        }
        return $out;
    }

    private static function getAllCustomizedSpritesInProject($json)
    {
        $out = [];
        $out["sprites"] = [];
        $count = 0;
        foreach ($json["pages"] as $page) {
            $out["pages"][$page] = [];
            foreach ($json[$page]["sprites"] as $sprite) {
                $out["pages"][$page][] =
                    $json[$page][$sprite]["md5"] ??
                    ($json[$page][$sprite]["text"] ?? null);
            }
        }
        return $out;
    }

    private static function findBlockCategory($block)
    {
        foreach (BLOCKS as $category => $blocks) {
            if (in_array($block, $blocks)) {
                return $category;
            }
        }
        return null;
    }

    private static function calculateProgramSyntaxLengthAmount($entry)
    {
        $programAmount = 0;
        $correctPrograms = [];

        $json = $entry["json"];
        if (isset($json["pages"])) {
            foreach ($json["pages"] as $page) {
                //On each page
                $pageBlocks = self::getAllBlocksOnPage($json[$page]);
                if (isset($json[$page]["sprites"])) {
                    foreach ($json[$page]["sprites"] as $sprite) {
                        //In each sprite
                        if (isset($json[$page][$sprite]["scripts"])) {
                            foreach (
                                $json[$page][$sprite]["scripts"]
                                as $script
                            ) {
                                $correctscript = true;
                                if (
                                    sizeof($script) > 1 &&
                                    in_array(
                                        $script[0][0],
                                        array_slice(
                                            BLOCKS["yellow_start"],
                                            0,
                                            4,
                                        ),
                                    )
                                ) {
                                    $programAmount = $programAmount + 1;
                                } else {
                                    $correctscript = false;
                                }
                                foreach ($script as $block) {
                                    if (
                                        $block[0] === "repeat" &&
                                        isset($block[4])
                                    ) {
                                        foreach ($block[4] as $blockInRepeat) {
                                            if (
                                                !self::checkCorrectness(
                                                    $blockInRepeat,
                                                    $json,
                                                    $page,
                                                    $sprite,
                                                    $script,
                                                    $pageBlocks,
                                                )
                                            ) {
                                                $correctscript = false;
                                            }
                                        }
                                    }
                                    if (
                                        !self::checkCorrectness(
                                            $block,
                                            $json,
                                            $page,
                                            $sprite,
                                            $script,
                                            $pageBlocks,
                                        )
                                    ) {
                                        $correctscript = false;
                                    }
                                }
                                if ($correctscript) {
                                    $correctPrograms[] = $script;
                                }
                            }
                        } else {
                        }
                    }
                } else {
                }
            }
        } else {
        }
        $categoryScore = 0;

        if ($programAmount > 0) {
            if ($programAmount >= 6 || sizeof($correctPrograms) >= 4) {
                $categoryScore = 4;
            } elseif ($programAmount >= 4 || sizeof($correctPrograms) >= 2) {
                $categoryScore = 3;
            } elseif ($programAmount >= 2 || sizeof($correctPrograms) === 1) {
                $categoryScore = 2;
            } elseif ($programAmount === 1) {
                $categoryScore = 1;
            }
        }
        $out = [
            "categoryScore" => $categoryScore,
            "programAmount" => $programAmount,
            "correctPrograms" => sizeof($correctPrograms),
        ];
        return $out;
    }

    private static function calculateVarietyOfBlocks($entry)
    {
        $categories = [];

        $json = $entry["json"];
        if (isset($json["pages"])) {
            foreach ($json["pages"] as $page) {
                //On each page
                if (isset($json[$page]["sprites"])) {
                    foreach ($json[$page]["sprites"] as $sprite) {
                        //In each sprite
                        if (isset($json[$page][$sprite]["scripts"])) {
                            foreach (
                                $json[$page][$sprite]["scripts"]
                                as $script
                            ) {
                                foreach ($script as $block) {
                                    if (
                                        $block[0] === "repeat" &&
                                        isset($block[4])
                                    ) {
                                        foreach ($block[4] as $blockInRepeat) {
                                            $category = self::findBlockCategory(
                                                $blockInRepeat[0],
                                            );
                                            if (
                                                !isset($categories[$category])
                                            ) {
                                                $categories[$category] = [];
                                            }
                                            if (
                                                !in_array(
                                                    $blockInRepeat[0],
                                                    $categories[$category],
                                                )
                                            ) {
                                                $categories[$category][] =
                                                    $blockInRepeat[0];
                                            }
                                        }
                                    } else {
                                        $category = self::findBlockCategory(
                                            $block[0],
                                        );
                                        if (!isset($categories[$category])) {
                                            $categories[$category] = [];
                                        }
                                        if (
                                            !in_array(
                                                $block[0],
                                                $categories[$category],
                                            )
                                        ) {
                                            $categories[$category][] =
                                                $block[0];
                                        }
                                    }
                                }
                            }
                        } else {
                        }
                    }
                } else {
                }
            }
        } else {
        }

        $categoryScore = 0;

        $distinctArray = [];
        foreach ($categories as $category) {
            $distinctArray[] = sizeof($category);
        }

        $countTwoOrMore = count(
            array_filter($distinctArray, function ($value) {
                return $value >= 2;
            }),
        );

        $countThreeOrMore = count(
            array_filter($distinctArray, function ($value) {
                return $value >= 3;
            }),
        );

        if ($countTwoOrMore >= 3) {
            $categoryScore = 4;
        } elseif ($countTwoOrMore >= 2) {
            $categoryScore = 3;
        } elseif ($countTwoOrMore >= 1) {
            if ($countThreeOrMore >= 1) {
                $categoryScore = 2;
            } else {
                $categoryScore = 1;
            }
        }

        $out = [
            "categoryScore" => $categoryScore,
            "countTwoOrMore" => $countTwoOrMore,
            "countThreeOrMore" => $countThreeOrMore,
        ];
        return $out;
    }

    private static function calculateCoordination($entry)
    {
        $characters = [];
        $greenFlagPresent = false;

        $json = $entry["json"];
        if (isset($json["pages"])) {
            foreach ($json["pages"] as $page) {
                //On each page

                if (isset($json[$page]["sprites"])) {
                    foreach ($json[$page]["sprites"] as $sprite) {
                        //In each sprite
                        $programAmount = 0;
                        $hasStartNotFlag = false;
                        $hasStartFlag = false;
                        if (isset($json[$page][$sprite]["scripts"])) {
                            foreach (
                                $json[$page][$sprite]["scripts"]
                                as $script
                            ) {
                                if (
                                    sizeof($script) > 1 &&
                                    in_array(
                                        $script[0][0],
                                        array_slice(
                                            BLOCKS["yellow_start"],
                                            0,
                                            4,
                                        ),
                                    )
                                ) {
                                    $programAmount = $programAmount + 1;
                                    if ($script[0][0] !== "onflag") {
                                        $hasStartNotFlag = true;
                                    } else {
                                        $hasStartFlag = true;
                                        $greenFlagPresent = true;
                                    }
                                }
                            }
                        } else {
                        }
                        if (!isset($characters[$page])) {
                            $characters[$page] = [];
                        }
                        $characters[$page][] = [
                            "character" => $json[$page][$sprite],
                            "programsInCharacter" => $programAmount,
                            "hasStartNotFlag" => $hasStartNotFlag,
                            "hasStartFlag" => $hasStartFlag,
                        ];
                    }
                } else {
                }
            }
        } else {
        }
        $categoryScore = 0;

        $oneCharacterPerPageHasProgram =
            count(
                array_filter($characters, function ($value) {
                    foreach ($value as $sprite) {
                        if ($sprite["programsInCharacter"] > 0) {
                            return true;
                        }
                    }
                    return false;
                }),
            ) === sizeof($characters);

        $twoCharactersWithProgramsSamePage =
            count(
                array_filter($characters, function ($value) {
                    $countForPage = 0;
                    foreach ($value as $sprite) {
                        if ($sprite["programsInCharacter"] > 0) {
                            $countForPage = $countForPage + 1;
                        }
                    }
                    return $countForPage >= 2;
                }),
            ) >= 1;

        $goToPageWithFlagNextPage =
            count(
                array_filter($characters, function ($value) use (
                    $characters,
                    $json,
                ) {
                    foreach ($value as $sprite) {
                        $blocks = self::getAllBlocksOnSprite(
                            $sprite["character"],
                        );
                        foreach ($blocks as $block) {
                            if ($block[0] === "gotopage") {
                                foreach (
                                    $characters[$json["pages"][$block[1] - 1]]
                                    as $sprite
                                ) {
                                    if ($sprite["hasStartFlag"]) {
                                        return true;
                                    }
                                }
                            }
                        }
                    }
                    return false;
                }),
            ) >= 1;

        $oneCharacterWithTwoOrMorePrograms =
            count(
                array_filter($characters, function ($value) {
                    foreach ($value as $sprite) {
                        if ($sprite["programsInCharacter"] >= 2) {
                            return true;
                        }
                    }
                    return false;
                }),
            ) >= 1;

        $oneOrMoreCharacterWithStartNotFlag =
            count(
                array_filter($characters, function ($value) {
                    foreach ($value as $sprite) {
                        if (
                            $sprite["programsInCharacter"] >= 2 &&
                            $sprite["hasStartNotFlag"]
                        ) {
                            return true;
                        }
                    }
                    return false;
                }),
            ) >= 1;

        if ($oneOrMoreCharacterWithStartNotFlag) {
            $categoryScore = 4;
        } elseif ($oneCharacterWithTwoOrMorePrograms) {
            $categoryScore = 3;
        } elseif (
            $twoCharactersWithProgramsSamePage ||
            $goToPageWithFlagNextPage
        ) {
            $categoryScore = 2;
        } elseif ($oneCharacterPerPageHasProgram || $greenFlagPresent) {
            $categoryScore = 1;
        }

        $out = [
            "categoryScore" => $categoryScore,
            "oneOrMoreCharacterWithStartNotFlag" => $oneOrMoreCharacterWithStartNotFlag,
            "oneCharacterWithTwoOrMorePrograms" => $oneCharacterWithTwoOrMorePrograms,
            "twoCharactersWithProgramsSamePage" => $twoCharactersWithProgramsSamePage,
            "goToPageWithFlagNextPage" => $goToPageWithFlagNextPage,
            "greenFlagPresent" => $greenFlagPresent,
            "oneCharacterPerPageHasProgram" => $oneCharacterPerPageHasProgram,
        ];
        return $out;
    }

    private static function calculateRepeatNumberParameters($entry)
    {
        $nonDefaultBlockAmount = 0;
        $usedRepeatForever = false;
        $correctRepeatWithOneBlock = 0;
        $correctRepeatWithMoreBlocks = 0;
        $negativeOrZeroParameter = 0;
        $correctNestedLoop = false;

        $json = $entry["json"];
        if (isset($json["pages"])) {
            foreach ($json["pages"] as $page) {
                //On each page
                $pageBlocks = self::getAllBlocksOnPage($json[$page]);
                if (isset($json[$page]["sprites"])) {
                    foreach ($json[$page]["sprites"] as $sprite) {
                        //In each sprite
                        if (isset($json[$page][$sprite]["scripts"])) {
                            foreach (
                                $json[$page][$sprite]["scripts"]
                                as $script
                            ) {
                                foreach ($script as $block) {
                                    if (!self::checkDefaults($block)) {
                                        $nonDefaultBlockAmount++;
                                    }
                                    if (self::checkNegativeOrZero($block)) {
                                        $negativeOrZeroParameter++;
                                    }
                                    if (
                                        $block[0] === "forever" &&
                                        sizeof($script) > 1
                                    ) {
                                        $usedRepeatForever = true;
                                    }
                                    if ($block[0] === "repeat") {
                                        if (
                                            self::checkCorrectness(
                                                $block,
                                                $json,
                                                $page,
                                                $sprite,
                                                $script,
                                                $pageBlocks,
                                            )
                                        ) {
                                            if (sizeof($block[4]) === 1) {
                                                $correctRepeatWithOneBlock++;
                                            } elseif (sizeof($block[4]) > 1) {
                                                $correctRepeatWithMoreBlocks++;
                                            }
                                        }
                                    }
                                    //TODO: Nested loops correctness
                                }
                            }
                        } else {
                        }
                    }
                } else {
                }
            }
        } else {
        }

        $categoryScore = 0;

        if ($negativeOrZeroParameter > 0) {
            $categoryScore = 4;
        } elseif ($correctRepeatWithMoreBlocks > 0) {
            $categoryScore = 3;
        } elseif ($correctRepeatWithOneBlock > 0 || $usedRepeatForever) {
            $categoryScore = 2;
        } elseif ($nonDefaultBlockAmount > 0) {
            $categoryScore = 1;
        }

        return [
            "categoryScore" => $categoryScore,
            "nonDefaultBlockAmount" => $nonDefaultBlockAmount,
            "usedRepeatForever" => $usedRepeatForever,
            "correctRepeatWithOneBlock" => $correctRepeatWithOneBlock,
            "correctRepeatWithMoreBlocks" => $correctRepeatWithMoreBlocks,
            "negativeOrZeroParameter" => $negativeOrZeroParameter,
        ];
    }

    private static function calculateVisualCustomization($entry, $extractPath)
    {
        $customizedPages = [];

        $backgroundsChanged = 0;
        $json = $entry["json"];

        if (isset($json["pages"])) {
            foreach ($json["pages"] as $page) {
                //On each page
                if (isset($json[$page]["md5"])) {
                    $backgroundsChanged++;
                }
            }
        } else {
        }

        $changedCharacters = self::getAllCustomizedSpritesInProject($json); //All characters that aren't Cat.svg
        $customizedCharacters = []; //Characters that involved the paint editor

        return ["categoryScore" => null];
    }

    private static function calculateProgrammedCustomization($entry)
    {
        return ["categoryScore" => null];
    }

    /*
     * Checking for correctness, for some kinds of blocks, requires a view of the entire project
     */
    private static function checkCorrectness(
        $block,
        $project,
        $page,
        $sprite,
        $script,
        $blocksPerPage,
    ) {
        $lastIndex = sizeof($script) - 1;
        if (
            in_array(
                $block[0],
                array_merge(BLOCKS["green_sound"], BLOCKS["red_end"]),
            )
        ) {
            return true;
        } elseif (in_array($block[0], BLOCKS["yellow_start"])) {
            if ($block[0] === "ontouch") {
                return sizeof($project[$page]["sprites"]) > 1;
            } elseif ($block[0] === "onmessage") {
                $color = $block[1];
                return count(
                    array_filter($blocksPerPage, function ($value) use (
                        $color,
                        $block,
                    ) {
                        return $value !== $block &&
                            $value[0] === "message" &&
                            $value[1] === $color;
                    }),
                ) >= 1;
            } elseif ($block[0] === "message") {
                $color = $block[1];
                return count(
                    array_filter($blocksPerPage, function ($value) use (
                        $color,
                        $block,
                    ) {
                        return $value !== $block &&
                            $value[0] === "onmessage" &&
                            $value[1] === $color;
                    }),
                ) >= 1;
            } elseif ($block[0] === "onbump") {
                return sizeof($page["characters"]) > 1;
            } else {
                return true;
            }
        } elseif (in_array($block[0], BLOCKS["blue_motion"])) {
            if ($block[0] === "home") {
                return $block[2] === $block[3];
            } else {
                $currentSprite = $project[$page][$sprite];
                return $currentSprite["homex"] !== $currentSprite["xcoor"] ||
                    $currentSprite["homey"] !== $currentSprite["ycoor"];
            }
        } elseif (in_array($block[0], BLOCKS["purple_looks"])) {
            return true;
        } elseif (in_array($block[0], BLOCKS["orange_control"])) {
            if ($block[0] === "wait") {
                return $script[$lastIndex][0] === "endstack" ||
                    $script[$lastIndex] === $block[0];
            } elseif ($block[0] === "stopmine") {
                $presentBlocksNames = array_map(function ($value) {
                    return $value[0];
                }, self::getAllBlocksOnSprite($sprite));
                return array_intersect(
                    $presentBlocksNames,
                    BLOCKS["blue_motion"],
                ) >= 1;
            } elseif ($block[0] === "setspeed") {
                $currentSprite = $project[$page][$sprite];
                return $currentSprite["speed"] !== $block[1];
            } elseif ($block[0] === "repeat") {
                if ($block[4] === []) {
                    return false;
                } elseif ($block[1] <= 0) {
                    return false;
                } elseif (sizeof($script) === 2 && $script[$lastIndex]) {
                    return false;
                } else {
                    return true;
                }
            }
        }
    }

    private static function checkCorrectnessLoop(
        $block,
        $project,
        $page,
        $sprite,
        $script,
        $blocksPerPage,
    ) {}

    private static function checkIdentity($blockOne, $blockTwo)
    {
        if ($blockOne[0] !== $blockTwo[0]) {
            return false;
        } else {
            return true;
        }
    }

    private static function checkDefaults($block)
    {
        if ($block[0] === "say") {
            return in_array($block[1], DEFAULTSVALUES[$block[0]]);
        } else {
            return $block[1] === DEFAULTSVALUES[$block[0]];
        }
    }

    private static function checkNegativeOrZero($block)
    {
        return $block[1] <= 0;
    }
}
