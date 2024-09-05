<?php

    /**
     * @api {get} /tt/issue get issues by filter
     *
     * @apiVersion 1.0.0
     *
     * @apiName getIssues
     * @apiGroup tt
     *
     * @apiHeader {String} authorization authentication token
     *
     * @apiQuery {String} filter
     * @apiQuery {String} search
     * @apiQuery {String} parent
     *
     * @apiSuccess {Object[]} issues
     */

    /**
     * @api {post} /tt/issue get issues by query
     *
     * @apiVersion 1.0.0
     *
     * @apiName getIssues
     * @apiGroup tt
     *
     * @apiHeader {String} authorization authentication token
     *
     * @apiBody {String} project
     * @apiBody {Object} query
     * @apiBody {Object} fields
     * @apiBody {Object} [sort]
     * @apiBody {Number} [skip=0]
     * @apiBody {Number} [limit=5]
     * @apiBody {Object} [preprocess]
     *
     * @apiSuccess {Object[]} issues
     */

    /**
     * tt api
     */

    namespace api\tt {

        use api\api;

        /**
         * tt issues count and bodies
         */

        class issues extends api {

            public static function GET($params) {
                $issues = [];

                $tt = loadBackend("tt");

                if ($tt && @$params["filter"] && $params["filter"] != "empty") {
                    try {
                        $filter = @json_decode($tt->getFilter($params["filter"]), true);
                        if ($filter) {
                            $preprocess = [];

                            if ($params && array_key_exists("search", $params) && trim($params["search"])) {
                                $preprocess["%%search"] = trim($params["search"]);
                            }

                            if ($params && array_key_exists("parent", $params) && trim($params["parent"])) {
                                $preprocess["%%parent"] = trim($params["parent"]);
                            }

                            if (@$filter["pipeline"]) {
                                $issues = $tt->getIssues(@$params["project"] ? : "TT", @$filter["pipeline"], @$filter["fields"], [], @$params["skip"] ? : 0, @$params["limit"] ? : 5, $preprocess, [], true);
                            } else {
                                $issues = $tt->getIssues(@$params["project"] ? : "TT", @$filter["filter"], @$filter["fields"], @$params["sort"] ? : (array_key_exists("sort", $filter) ? $filter["sort"] : [ "created" => 1 ]), @$params["skip"] ? : 0, @$params["limit"] ? : 5, $preprocess);
                            }
                        } else {
                            setLastError("filterNotFound");
                            return api::ERROR();
                        }
                    } catch (\Exception $e) {
                        setLastError($e->getMessage());
                        $issues["exception"] = $e->getMessage();
                        return api::ANSWER($issues, "issues");
                    }
                }

                return api::ANSWER($issues, ($issues !== false) ? "issues" : "notFound");
            }

            public static function POST($params) {
                $issues = [];

                $tt = loadBackend("tt");

                if ($tt && @$params["query"]) {
                    try {
                        $issues = $tt->getIssues(@$params["project"] ? : "TT", @$params["query"], @$params["fields"], @$params["sort"] ? : [ "created" => 1 ], @$params["skip"] ? : 0, @$params["limit"] ? : 5, @$params["preprocess"] ? : []);
                    } catch (\Exception $e) {
                        setLastError($e->getMessage());
                        return api::ERROR();
                    }
                }

                return api::ANSWER($issues, ($issues !== false) ? "issues" : "notFound");
            }

            public static function index() {
                if (loadBackend("tt")) {
                    return [
                        "GET" => "#same(tt,issue,GET)",
                        "POST" => "#same(tt,issue,GET)",
                    ];
                } else {
                    return false;
                }
            }
        }
    }
