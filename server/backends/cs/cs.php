<?php

    /**
     * backends cs namespace
     */

    namespace backends\cs {

        use backends\backend;

        /**
         * base cs class
         */

        abstract class cs extends backend {
            /**
             * @param $sheet
             * @param $date
             * @return mixed
             */
            public function getCS($sheet, $date, $extended = false)
            {
                $files = loadBackend("files");

                if (!$files) {
                    return false;
                }

                $css = $files->searchFiles([
                    "metadata.type" => "csheet",
                    "metadata.sheet" => $sheet,
                    "metadata.date" => $date,
                ]);

                $cs = "{\n\t\"sheet\": \"$sheet\",\n\t\"date\": \"$date\"\n}";

                foreach ($css as $s) {
                    $cs = $files->streamToContents($files->getFileStream($s["id"])) ? : $cs;
                    break;
                }

                if ($extended) {
                    return [
                        "sheet" => json_decode($cs),
                        "cells" => [ ],
                    ];
                } else {
                    return $cs;
                }
            }

            /**
             * @param $sheet
             * @param $date
             * @param $data
             * @return boolean
             */
            public function putCS($sheet, $date, $data)
            {
                $files = loadBackend("files");

                if (!$files) {
                    return false;
                }

                $css = $files->searchFiles([
                    "metadata.type" => "csheet",
                    "metadata.sheet" => $sheet,
                    "metadata.date" => $date,
                ]);

                foreach ($css as $s) {
                    $cs = $files->deleteFile($s["id"]);
                }

                return $files->addFile($date . "_" . $sheet . ".json", $files->contentsToStream($data), [
                    "type" => "csheet",
                    "sheet" => $sheet,
                    "date" => $date,
                ]);
            }

            /**
             * @param $date
             * @return boolean
             */
            public function deleteCS($sheet, $date)
            {
                $files = loadBackend("files");

                if (!$files) {
                    return false;
                }

                $css = $files->searchFiles([
                    "metadata.type" => "csheet",
                    "metadata.sheet" => $sheet,
                    "metadata.date" => $date,
                ]);

                foreach ($css as $s) {
                    $cs = $files->deleteFile($s["id"]);
                }

                return true;
            }

            /**
             * @return false|array
             */
            public function getCSes()
            {
                $files = loadBackend("files");

                if (!$files) {
                    return false;
                }

                return $files->searchFiles([
                    "metadata.type" => "csheet",
                ]);
            }

            /**
             * @param $action
             * @param $sheet
             * @param $date
             * @param $col
             * @param $row
             * @param $uid
             * @param $expire
             */
            public function setCell($action, $sheet, $date, $col, $row, $uid, $expire = 0)
            {
                $expire = (int)($expire ? : 60);
                switch ($action) {
                    case "claim":
                    case "release":
                    case "release-force":
                        $keys = $this->redis->keys("cell_{$sheet}_{$date}_*");

                        foreach ($keys as $key) {
                            $cell = json_decode($this->redis->get($key), true);
                            if (
                                ($action == "claim" && $cell["login"] == $this->login && $cell["mode"] == "claimed") ||
                                ($action == "release" && $cell["login"] == $this->login && $cell["mode"] == "claimed") ||
                                ($action == "release" && $cell["login"] == $this->login && $cell["mode"] == "reserved" && $cell["uid"] == $uid) ||
                                ($action == "release-force" && $cell["mode"] == "reserved" && $cell["uid"] == $uid)
                            ) {
                                $this->redis->delete($key);
                                $payload = explode("_", $key);
                                file_get_contents("http://127.0.0.1:8082/broadcast", false, stream_context_create([
                                    'http' => [
                                        'method'  => 'POST',
                                        'header'  => [
                                            'Content-Type: application/json; charset=utf-8',
                                            'Accept: application/json; charset=utf-8',
                                        ],
                                        'content' => json_encode([
                                            "topic" => "cs/cell",
                                            "payload" => [
                                                "action" => "released",
                                                "sheet" => $payload[1],
                                                "date" => $payload[2],
                                                "col" => $payload[3],
                                                "row" => $payload[4],
                                                "uid" => $payload[5],
                                            ],
                                        ]),
                                    ],
                                ]));
                            }
                        }

                        if ($action == "claim") {
                            $this->redis->setex("cell_{$sheet}_{$date}_{$col}_{$row}_{$uid}", $expire, json_encode([
                                "login" => $this->login,
                                "mode" => "claimed",
                                "expire" => $expire,
                                "claimed" => time(),
                            ]));

                            file_get_contents("http://127.0.0.1:8082/broadcast", false, stream_context_create([
                                'http' => [
                                    'method'  => 'POST',
                                    'header'  => [
                                        'Content-Type: application/json; charset=utf-8',
                                        'Accept: application/json; charset=utf-8',
                                    ],
                                    'content' => json_encode([
                                        "topic" => "cs/cell",
                                        "payload" => [
                                            "action" => "claimed",
                                            "sheet" => $sheet,
                                            "date" => $date,
                                            "col" => $col,
                                            "row" => $row,
                                            "uid" => $uid,
                                            "login" => $this->login,
                                        ],
                                    ]),
                                ],
                            ]));
                        }

                        break;

                    case "reserve":
                        try {
                            $cell = json_decode($this->redis->get($this->redis->keys("cell_*_" . $uid)[0]), true);
                        } catch (\Exception $e) {
                            $cell = false;
                        }

                        if ($cell && $cell["login"] == $this->login) {
                            $this->redis->setex("cell_{$sheet}_{$date}_{$col}_{$row}_{$uid}", $expire, json_encode([
                                "login" => $this->login,
                                "mode" => "reserved",
                                "expire" => $expire,
                                "reserved" => time(),
                            ]));
    
                            file_get_contents("http://127.0.0.1:8082/broadcast", false, stream_context_create([
                                'http' => [
                                    'method'  => 'POST',
                                    'header'  => [
                                        'Content-Type: application/json; charset=utf-8',
                                        'Accept: application/json; charset=utf-8',
                                    ],
                                    'content' => json_encode([
                                        "topic" => "cs/cell",
                                        "payload" => [
                                            "action" => "reserved",
                                            "sheet" => $sheet,
                                            "date" => $date,
                                            "col" => $col,
                                            "row" => $row,
                                            "uid" => $uid,
                                            "login" => $this->login,
                                        ],
                                    ]),
                                ],
                            ]));
                        }

                        break;
                }

                return true;
            }

            /**
             * @param $sheet
             * @param $date
             * @param $col
             * @param $row
             */
            public function getCellByXYZ($sheet, $date, $col, $row)
            {

            }

            /**
             * @param $uid
             */
            public function getCellByUID($uid)
            {

            }

            /**
             * @param $sheet
             * @param $date
             */
            public function cells($sheet, $date)
            {

            }
            
        }
    }