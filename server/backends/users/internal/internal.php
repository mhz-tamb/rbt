<?php

    /**
     * backends users namespace
     */

    namespace backends\users {

        use PHPMailer\PHPMailer\Exception;

        /**
         * internal users class
         */

        class internal extends users {

            /**
             * list of all users
             *
             * @return array|false
             */

            public function getUsers() {
                try {
                    $users = $this->db->query("select uid, login, real_name, e_mail, phone from users order by uid", \PDO::FETCH_ASSOC)->fetchAll();
                    $_users = [];

                    foreach ($users as $user) {
                        $_users[] = [
                            "uid" => $user["uid"],
                            "login" => $user["login"],
                            "realName" => $user["real_name"],
                            "eMail" => $user["e_mail"],
                            "phone" => $user["phone"],
                        ];
                    }

                    return $_users;
                } catch (Exception $e) {
                    return false;
                }
            }

            /**
             * get user by uid
             *
             * @param integer $uid uid
             *
             * @return array|false
             */

            public function getUser($uid) {
                if (!checkInt($uid)) {
                    return false;
                }

                try {
                    $user = $this->db->query("select uid, login, real_name, e_mail, phone from users where uid = $uid", \PDO::FETCH_ASSOC)->fetchAll();

                    if (count($user)) {
                        $_user = [
                            "uid" => $user[0]["uid"],
                            "login" => $user[0]["login"],
                            "realName" => $user[0]["real_name"],
                            "eMail" => $user[0]["e_mail"],
                            "phone" => $user[0]["phone"],
                        ];

                        $groups = loadBackend("groups");

                        if ($groups !== false) {
                            $_groups = $groups->getGroups($uid);
                            $_user["groups"] = [];
                            foreach ($_groups as $group) {
                                $_user["groups"][$group["gid"]] = [
                                    "groupName" => $group["groupName"],
                                ];
                            }
                        }

                        return $_user;
                    } else {
                        return false;
                    }
                } catch (Exception $e) {
                    return false;
                }
            }

            /**
             * add user
             *
             * @param string $login
             * @param string $password
             * @param string $realName
             * @param string $eMail
             * @param string $phone
             *
             * @return integer|false
             */

            public function addUser($login, $password, $realName = '', $eMail = '', $phone = '') {
                $login = trim($login);

                try {
                    $sth = $this->db->prepare("insert into users (login, password, real_name, e_mail, phone, enabled) values (:login, :password, :real_name, :phone, 1)");
                    if (!$sth->execute([
                        ":login" => $login,
                        ":password" => password_hash($password, PASSWORD_DEFAULT),
                        ":real_name" => trim($realName),
                        ":e_mail" => trim($eMail),
                        ":phone" => trim($phone),
                    ])) {
                        return false;
                    }

                    $sth = $this->db->prepare("select uid from users where login = :login");
                    $uid = $sth->execute([
                        ":login" => $login,
                    ]);

                    if ($uid > 0) { // 0 reserved for admin
                        return $uid;
                    } else {
                        return false;
                    }
                } catch (Exception $e) {
                    return false;
                }
            }

            /**
             * set password
             *
             * @param integer $uid
             * @param string $password
             *
             * @return boolean
             */

            public function setPassword($uid, $password) {
                if (!checkInt($uid)) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("update users set password = :password where uid = $uid");
                    $sth->execute([ ":password" => password_hash($password, PASSWORD_DEFAULT) ]);
                } catch (Exception $e) {
                    return false;
                }

                return true;
            }

            /**
             * delete user
             *
             * @param $uid
             *
             * @return boolean
             */

            public function removeUser($uid) {
                if (!checkInt($uid)) {
                    return false;
                }

                if ($uid > 0) { // admin cannot be deleted
                    try {
                        $this->db->exec("delete from users where uid = $uid");
                        $this->db->exec("delete from users_groups where uid = $uid");
                    } catch (Exception $e) {
                        return false;
                    }
                    return true;
                } else {
                    return false;
                }
            }

            /**
             * modify user data
             *
             * @param integer $uid
             * @param string $realName
             * @param string $eMail
             * @param string $phone
             *
             * @return boolean
             */

            public function modifyUser($uid, $realName = '', $eMail = '', $phone = '') {
                if (!checkInt($uid)) {
                    return false;
                }

                try {
                    $sth = $this->db->prepare("update users set login = :login, password = :password, real_name = :real_name, e_mail = :e_mail, phone = :phone where uid = :uid");
                    return $sth->execute([
                        ":uid" => $uid,
                        ":real_name" => trim($realName),
                        ":e_mail" => trim($eMail),
                        ":phone" => trim($phone),
                    ]);
                } catch (Exception $e) {
                    return false;
                }

                return true;
            }

            /**
             * enable or disable user
             *
             * @param integer $uid
             * @param boolean $enable
             *
             * @return boolean
             */

            public function enableUser($uid, $enable = true) {
                if (!checkInt($uid)) {
                    return false;
                }

                try {
                    if ($enable) {
                        $this->db->exec("update users set enabled = 1 where uid = $uid");
                    } else {
                        $this->db->exec("update users set enabled = 0 where uid = $uid");
                    }
                } catch (Exception $e) {
                    return false;
                }

                return true;
            }

            /**
             * get uid by e-mail
             *
             * @param string $eMail e-mail
             *
             * @return false|integer
             */

            public function getUidByEMail($eMail) {
                try {
                    $sth = $this->db->prepare("select uid from users where e_mail = :e_mail");
                    if ($sth->execute([ ":e_mail" => $eMail ])) {
                        $users = $sth->fetchAll(\PDO::FETCH_ASSOC);
                        if (count($users)) {
                            return (int)$users[0]["uid"];
                        } else {
                            return false;
                        }
                    } else {
                        return false;
                    }
                } catch (Exception $e) {
                    return false;
                }
            }

            /**
             * returns class capabilities
             *
             * @return mixed
             */

            public function capabilities() {
                return [
                    "mode" => "rw",
                ];
            }
        }
    }
