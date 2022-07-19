<?php


    $dbh;

    header("Access-Control-Allow-Headers: Authorization");
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=utf-8");
    header("X-Content-Type-Options: nosniff");

    $_SERVER["PATH_INFO"] = get_path_info();

    # /events
    if (strcmp("events", $_SERVER["PATH_INFO"]) == 0)
    {
        switch ($_SERVER["REQUEST_METHOD"])
        {
            # 大会一覧を取得
            case "GET":
                authorize();

                $response = array();

                $stmt = $dbh->query("
                    SELECT
                        events.id,
                        events.name,
                        MIN(schedules.date) AS 'date_start',
                        MAX(schedules.date) AS 'date_end'
                    FROM
                        events
                    LEFT JOIN
                        schedules
                    ON
                        events.id = schedules.event_id
                    GROUP BY
                        events.id
                    ORDER BY
                        events.id
                    ;
                ");

                foreach ($stmt as $row)
                {
                    $response[] = array(
                        "id"=> (int)$row["id"],
                        "name"=> $row["name"],
                        "startDate"=> is_null($row["date_start"]) ? null : (int)substr($row["date_start"], 0, 8),
                        "endDate"=> is_null($row["date_end"]) ? null : (int)substr($row["date_end"], 0, 8)
                    );
                }

                echo(json_encode($response));

                break;

            case "OPTIONS":
                break;

            default:
                http_response_code(405);
        }
    }

    # /events/:id
    else if (preg_match("#^events/(\d+)$#", $_SERVER["PATH_INFO"], $matches))
    {
        switch ($_SERVER["REQUEST_METHOD"])
        {
            # 大会情報を取得
            case "GET":
                authorize();

                $response = array();

                $stmt = $dbh->prepare("
                    SELECT
                        id,
                        name
                    FROM
                        events
                    WHERE
                        id = ?
                    ;
                ");
                $stmt->bindValue(1, (int)$matches[1], PDO::PARAM_INT);
                $stmt->execute();

                $row = $stmt->fetch();
                if (!$row)
                {
                    http_response_code(404);
                    exit();
                }
                else
                {
                    $response["id"] = (int)$row["id"];
                    $response["name"] = $row["name"];
                }

                $schedules = array();

                $stmt = $dbh->prepare("
                    SELECT
                        schedules.id AS 'schedule_id',
                        schedules.name,
                        schedules.date,
                        games.id AS 'game_id',
                        games.room_name,
                        games.entry_id_affirmative,
                        games.entry_id_negative,
                        games.vote_affirmative,
                        games.vote_negative,
                        games.comm_affirmative,
                        games.comm_negative
                    FROM
                        schedules
                    LEFT JOIN
                        (
                            SELECT
                                games.id,
                                games.room_name,
                                games.entry_id_affirmative,
                                games.entry_id_negative,
                                games.schedule_id,
                                SUM(results.vote_affirmative) AS 'vote_affirmative',
                                SUM(results.vote_negative) AS 'vote_negative',
                                SUM(
                                    results.constructive_affirmative +
                                    results.questions_affirmative +
                                    results.answers_affirmative +
                                    results.rebuttal1_affirmative +
                                    results.rebuttal2_affirmative +
                                    results.manner_affirmative
                                ) AS 'comm_affirmative',
                                SUM(
                                    results.constructive_negative +
                                    results.questions_negative +
                                    results.answers_negative +
                                    results.rebuttal1_negative +
                                    results.rebuttal2_negative +
                                    results.manner_negative
                                ) AS 'comm_negative'
                            FROM
                                games
                            LEFT JOIN
                                results
                            ON
                                games.id = results.game_id
                            WHERE
                                games.event_id = ?
                            GROUP BY
                                games.id
                        ) games
                    ON
                        schedules.id = games.schedule_id
                    WHERE
                        schedules.event_id = ?
                    ORDER BY
                        schedules.date,
                        games.room_name
                    ;
                ");
                $stmt->bindValue(1, (int)$matches[1], PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$matches[1], PDO::PARAM_INT);
                $stmt->execute();

                foreach ($stmt as $row)
                {
                    $lastSchedule = end($schedules);
                    if (!$lastSchedule || $lastSchedule["id"] !== (int)$row["schedule_id"])
                    {
                        $schedule = array();
                        $schedule["id"] = (int)$row["schedule_id"];
                        $schedule["name"] = $row["name"];
                        $schedule["startDate"] = (int)$row["date"];
                        $schedule["games"] = array();
                        $schedules[] = $schedule;
                    }

                    if (!is_null($row["game_id"]))
                    {
                        $schedules[count($schedules) - 1]["games"][] = array(
                            "id"=> (int)$row["game_id"],
                            "roomName"=> $row["room_name"],
                            "affirmativeTeamId"=> (int)$row["entry_id_affirmative"],
                            "negativeTeamId"=> (int)$row["entry_id_negative"],
                            "affirmativeVote"=> is_null($row["vote_affirmative"]) ? null : (int)$row["vote_affirmative"],
                            "negativeVote"=> is_null($row["vote_negative"]) ? null : (int)$row["vote_negative"],
                            "affirmativeCommPoint"=> is_null($row["comm_affirmative"]) ? null : (int)$row["comm_affirmative"],
                            "negativeCommPoint"=> is_null($row["comm_negative"]) ? null : (int)$row["comm_negative"]
                        );
                    }
                }

                $response["schedules"] = $schedules;

                $teams = array();

                $stmt = $dbh->prepare("
                    SELECT
                        id,
                        team_name,
                        class_id
                    FROM
                        entries
                    WHERE
                        event_id = ?
                    ORDER BY
                        class_id
                    ;
                ");
                $stmt->bindValue(1, (int)$matches[1], PDO::PARAM_INT);
                $stmt->execute();

                foreach ($stmt as $row)
                {
                    $teams[] = array(
                        "id"=> (int)$row["id"],
                        "name"=> $row["team_name"],
                        "classId"=> (int)$row["class_id"]
                    );
                }

                $response["teams"] = $teams;

                echo(json_encode($response));

                break;

            case "OPTIONS":
                break;

            default:
                http_response_code(405);
        }
    }

    # /events/:id/teams
    else if (preg_match("#^events/(\d+)/teams$#", $_SERVER["PATH_INFO"], $matches))
    {
        switch ($_SERVER["REQUEST_METHOD"])
        {
            # 出場校一覧を取得
            case "GET":
                authorize();

                $stmt = $dbh->prepare("
                    SELECT
                        1
                    FROM
                        events
                    WHERE
                        id = ?
                    ;
                ");
                $stmt->bindValue(1, (int)$matches[1], PDO::PARAM_INT);
                $stmt->execute();

                $row = $stmt->fetch();
                if (!$row)
                {
                    http_response_code(404);
                    exit();
                }

                $response = array();

                $sql = "
                    SELECT
                        entries.id,
                        entries.team_name,
                        entries.class_id,
                        COUNT(games.entry_id_affirmative) AS 'game_count',
                        COUNT(games.vote_affirmative) AS 'done_count',
                        SUM(
                            CASE 
                                WHEN entries.id = games.entry_id_affirmative AND games.vote_affirmative > games.vote_negative THEN
                                    1
                                WHEN entries.id = games.entry_id_negative AND games.vote_affirmative < games.vote_negative THEN
                                    1
                                ELSE
                                    0
                            END
                        ) AS 'win',
                        SUM(
                            CASE
                                WHEN entries.id = games.entry_id_affirmative THEN
                                    games.vote_affirmative
                                ELSE
                                    games.vote_negative
                            END
                        ) AS 'vote',
                        SUM(
                            CASE WHEN entries.id = games.entry_id_affirmative THEN
                                games.comm_affirmative
                            ELSE
                                games.comm_negative
                            END
                        ) AS 'comm_point'
                    FROM
                        entries
                    LEFT JOIN
                        (
                            SELECT
                                games.entry_id_affirmative,
                                games.entry_id_negative,
                                SUM(results.vote_affirmative) AS 'vote_affirmative',
                                SUM(results.vote_negative) AS 'vote_negative',
                                SUM(
                                    results.constructive_affirmative +
                                    results.questions_affirmative +
                                    results.answers_affirmative +
                                    results.rebuttal1_affirmative +
                                    results.rebuttal2_affirmative +
                                    results.manner_affirmative
                                ) AS 'comm_affirmative',
                                SUM(
                                    results.constructive_negative +
                                    results.questions_negative +
                                    results.answers_negative +
                                    results.rebuttal1_negative +
                                    results.rebuttal2_negative +
                                    results.manner_negative
                                ) AS 'comm_negative'
                            FROM
                                games
                            LEFT JOIN
                                results
                            ON
                                games.id = results.game_id
                            WHERE
                                games.event_id = ?
                            GROUP BY
                                games.id
                        ) games
                    ON
                        entries.id = games.entry_id_affirmative OR
                        entries.id = games.entry_id_negative
                    WHERE
                        entries.event_id = ?
                    GROUP BY
                        entries.id
                ";

                if (isset($_GET["sort"]))
                {
                    if (is_string($_GET["sort"]))
                    {
                        $orderby = "";
                        $params = explode(",", $_GET["sort"]);
                        foreach ($params as $param)
                        {
                            if ($param === "id")
                            {
                                $orderby .= "entries.id,";
                            }
                            else if ($param === "-id")
                            {
                                $orderby .= "entries.id DESC,";
                            }
                            else if ($param === "name")
                            {
                                $orderby .= "entries.team_name,";
                            }
                            else if ($param === "-name")
                            {
                                $orderby .= "entries.team_name DESC,";
                            }
                            else if ($param === "classid")
                            {
                                $orderby .= "entries.class_id,";
                            }
                            else if ($param === "-classid")
                            {
                                $orderby .= "entries.class_id DESC,";
                            }
                            else if ($param === "gamecount")
                            {
                                $orderby .= "game_count,";
                            }
                            else if ($param === "-gamecount")
                            {
                                $orderby .= "game_count DESC,";
                            }
                            else if ($param === "donecount")
                            {
                                $orderby .= "done_count,";
                            }
                            else if ($param === "-donecount")
                            {
                                $orderby .= "done_count DESC,";
                            }
                            else if ($param === "wincount")
                            {
                                $orderby .= "win,";
                            }
                            else if ($param === "-wincount")
                            {
                                $orderby .= "win DESC,";
                            }
                            else if ($param === "vote")
                            {
                                $orderby .= "vote,";
                            }
                            else if ($param === "-vote")
                            {
                                $orderby .= "vote DESC,";
                            }
                            else if ($param === "commpoint")
                            {
                                $orderby .= "comm_point,";
                            }
                            else if ($param === "-commpoint")
                            {
                                $orderby .= "comm_point DESC,";
                            }
                            else
                            {
                                http_response_code(400);
                                exit();
                            }
                        }
                        $sql .= "ORDER BY " . substr($orderby, 0, -1);
                    }
                    else
                    {
                        http_response_code(400);
                        exit();
                    }
                }

                $sql .= ";";

                $stmt = $dbh->prepare($sql);
                $stmt->bindValue(1, (int)$matches[1], PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$matches[1], PDO::PARAM_INT);
                $stmt->execute();

                foreach ($stmt as $row)
                {
                    $response[] = array(
                        "id"=> (int)$row["id"],
                        "name"=> $row["team_name"],
                        "classId"=> (int)$row["class_id"],
                        "gameCount"=> (int)$row["game_count"],
                        "doneCount"=> (int)$row["done_count"],
                        "winCount"=> (int)$row["win"],
                        "vote"=> is_null($row["vote"]) ? 0 : (int)$row["vote"],
                        "commPoint"=> is_null($row["comm_point"]) ? 0 : (int)$row["comm_point"]
                    );
                }

                echo(json_encode($response));

                break;

            case "OPTIONS":
                break;

            default:
                http_response_code(405);
        }
    }

    # /events/:id/games
    else if (preg_match("#^events/(\d+)/games$#", $_SERVER["PATH_INFO"], $matches))
    {
        switch ($_SERVER["REQUEST_METHOD"])
        {
            # 試合を登録
            case "POST":
                authorize();

                echo("POST /events/:id/games");

                break;

            case "OPTIONS":
                break;

            default:
                http_response_code(405);
        }
    }

    # /events/:id/games/:id
    else if (preg_match("#^events/(\d+)/games/(\d+)$#", $_SERVER["PATH_INFO"], $matches))
    {
        switch ($_SERVER["REQUEST_METHOD"])
        {
            # 試合情報を取得
            case "GET":
                authorize();

                $response = array();

                $stmt = $dbh->prepare("
                    SELECT
                        games.id,
                        games.room_name,
                        games.entry_id_affirmative,
                        games.entry_id_negative,
                        games.event_id,
                        games.schedule_id,
                        affirmative.team_name AS 'name_affirmative',
                        affirmative.class_id AS 'class_affirmative',
                        negative.team_name AS 'name_negative',
                        negative.class_id AS 'class_negative',
                        results.judge_name,
                        results.vote_affirmative,
                        results.constructive_affirmative,
                        results.questions_affirmative,
                        results.answers_affirmative,
                        results.rebuttal1_affirmative,
                        results.rebuttal2_affirmative,
                        results.manner_affirmative,
                        results.vote_negative,
                        results.constructive_negative,
                        results.questions_negative,
                        results.answers_negative,
                        results.rebuttal1_negative,
                        results.rebuttal2_negative,
                        results.manner_negative
                    FROM
                        games
                    INNER JOIN
                        entries affirmative
                    ON
                        ? = affirmative.event_id AND
                        games.entry_id_affirmative = affirmative.id
                    INNER JOIN
                        entries negative
                    ON
                        ? = negative.event_id AND
                        games.entry_id_negative = negative.id
                    LEFT JOIN
                        results
                    ON
                        games.id = results.game_id
                    WHERE
                        games.id = ?
                    ORDER BY
                        results.judge_name
                    ;
                ");
                $stmt->bindValue(1, (int)$matches[1], PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$matches[1], PDO::PARAM_INT);
                $stmt->bindValue(3, (int)$matches[2], PDO::PARAM_INT);
                $stmt->execute();

                $row = $stmt->fetch();
                if (!$row)
                {
                    http_response_code(404);
                    exit();
                }
                else
                {
                    $response["id"] = (int)$row["id"];
                    $response["roomName"] = $row["room_name"];
                    $response["affirmativeTeamId"] = (int)$row["entry_id_affirmative"];
                    $response["negativeTeamId"] = (int)$row["entry_id_negative"];
                    $response["eventId"] = (int)$row["event_id"];
                    $response["scheduleId"] = (int)$row["schedule_id"];
                    $response["affirmativeTeamName"] = $row["name_affirmative"];
                    $response["affirmativeClassId"] = (int)$row["class_affirmative"];
                    $response["negativeTeamName"] = $row["name_negative"];
                    $response["negativeClassId"] = (int)$row["class_negative"];
                    $response["results"] = array();

                    do
                    {
                        if (!is_null($row["judge_name"]))
                        {
                            $response["results"][] = array(
                                "judgeName"=> $row["judge_name"],
                                "vote"=> ((int)$row["vote_affirmative"]) === 1 ? "affirmative" : "negative",
                                "affirmativeConstructive"=> (int)$row["constructive_affirmative"],
                                "affirmativeQuestions"=> (int)$row["questions_affirmative"],
                                "affirmativeAnswers"=> (int)$row["answers_affirmative"],
                                "affirmativeRebuttal1"=> (int)$row["rebuttal1_affirmative"],
                                "affirmativeRebuttal2"=> (int)$row["rebuttal2_affirmative"],
                                "affirmativeManner"=> (int)$row["manner_affirmative"],
                                "negativeConstructive"=> (int)$row["constructive_negative"],
                                "negativeQuestions"=> (int)$row["questions_negative"],
                                "negativeAnswers"=> (int)$row["answers_negative"],
                                "negativeRebuttal1"=> (int)$row["rebuttal1_negative"],
                                "negativeRebuttal2"=> (int)$row["rebuttal2_negative"],
                                "negativeManner"=> (int)$row["manner_negative"]
                            );
                        }
                        $row = $stmt->fetch();
                    } while ($row);
                }

                echo(json_encode($response));

                break;

            # 試合結果を登録
            case "PATCH":
                authorize();

                $stmt = $dbh->prepare("
                    SELECT
                        1
                    FROM
                        games
                    WHERE
                        id = ? AND
                        event_id = ?
                    ;
                ");
                $stmt->bindValue(1, (int)$matches[2], PDO::PARAM_INT);
                $stmt->bindValue(2, (int)$matches[1], PDO::PARAM_INT);
                $stmt->execute();

                $row = $stmt->fetch();
                if (!$row)
                {
                    http_response_code(404);
                    exit();
                }

                $request = json_decode(file_get_contents("php://input"), true, 4);
                if (json_last_error() !== JSON_ERROR_NONE)
                {
                    http_response_code(400);
                    exit();
                }

                if (isset($request["results"]) && is_array($request["results"]))
                {
                    foreach ($request["results"] as $result)
                    {
                        if (!isset($result["judgeName"]) ||
                            !is_string($result["judgeName"]) ||
                            mb_strlen($result["judgeName"]) < 1 ||
                            mb_strlen($result["judgeName"]) > 255)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["vote"]) ||
                            !is_string($result["vote"]) ||
                            ($result["vote"] !== "affirmative" && $result["vote"] !== "negative"))
                        {
                            http_response_code(400);
                            exit();
                        }

                        if (!isset($result["affirmativeConstructive"]) ||
                            !is_int($result["affirmativeConstructive"]) ||
                            $result["affirmativeConstructive"] < 1 ||
                            $result["affirmativeConstructive"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["affirmativeQuestions"]) ||
                            !is_int($result["affirmativeQuestions"]) ||
                            $result["affirmativeQuestions"] < 1 ||
                            $result["affirmativeQuestions"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["affirmativeAnswers"]) ||
                            !is_int($result["affirmativeAnswers"]) ||
                            $result["affirmativeAnswers"] < 1 ||
                            $result["affirmativeAnswers"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["affirmativeRebuttal1"]) ||
                            !is_int($result["affirmativeRebuttal1"]) ||
                            $result["affirmativeRebuttal1"] < 1 ||
                            $result["affirmativeRebuttal1"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["affirmativeRebuttal2"]) ||
                            !is_int($result["affirmativeRebuttal2"]) ||
                            $result["affirmativeRebuttal2"] < 1 ||
                            $result["affirmativeRebuttal2"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["affirmativeManner"]) ||
                            !is_int($result["affirmativeManner"]) ||
                            $result["affirmativeManner"] < -5 ||
                            $result["affirmativeManner"] > 0)
                        {
                            http_response_code(400);
                            exit();
                        }

                        if (!isset($result["negativeConstructive"]) ||
                            !is_int($result["negativeConstructive"]) ||
                            $result["negativeConstructive"] < 1 ||
                            $result["negativeConstructive"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["negativeQuestions"]) ||
                            !is_int($result["negativeQuestions"]) ||
                            $result["negativeQuestions"] < 1 ||
                            $result["negativeQuestions"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["negativeAnswers"]) ||
                            !is_int($result["negativeAnswers"]) ||
                            $result["negativeAnswers"] < 1 ||
                            $result["negativeAnswers"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["negativeRebuttal1"]) ||
                            !is_int($result["negativeRebuttal1"]) ||
                            $result["negativeRebuttal1"] < 1 ||
                            $result["negativeRebuttal1"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["negativeRebuttal2"]) ||
                            !is_int($result["negativeRebuttal2"]) ||
                            $result["negativeRebuttal2"] < 1 ||
                            $result["negativeRebuttal2"] > 5)
                        {
                            http_response_code(400);
                            exit();
                        }
                        if (!isset($result["negativeManner"]) ||
                            !is_int($result["negativeManner"]) ||
                            $result["negativeManner"] < -5 ||
                            $result["negativeManner"] > 0)
                        {
                            http_response_code(400);
                            exit();
                        }
                    }
                }
                else
                {
                    http_response_code(400);
                    exit();
                }

                try
                {
                    $dbh->beginTransaction();

                    $stmt = $dbh->prepare("
                        DELETE FROM
                            results
                        WHERE
                            game_id = ?
                        ;
                    ");
                    $stmt->bindValue(1, (int)$matches[2], PDO::PARAM_INT);
                    $stmt->execute();

                    $stmt = $dbh->prepare("
                        INSERT INTO
                            results
                            (
                                game_id,
                                judge_name,
                                vote_affirmative,
                                constructive_affirmative,
                                questions_affirmative,
                                answers_affirmative,
                                rebuttal1_affirmative,
                                rebuttal2_affirmative,
                                manner_affirmative,
                                vote_negative,
                                constructive_negative,
                                questions_negative,
                                answers_negative,
                                rebuttal1_negative,
                                rebuttal2_negative,
                                manner_negative
                            )
                        VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?
                            )
                        ;
                    ");
                    foreach ($request["results"] as $result)
                    {
                        $stmt->bindValue(1, (int)$matches[2], PDO::PARAM_INT);
                        $stmt->bindValue(2, $result["judgeName"], PDO::PARAM_STR);
                        $stmt->bindValue(3, $result["vote"] === "affirmative" ? 1 : 0, PDO::PARAM_INT);
                        $stmt->bindValue(4, (int)$result["affirmativeConstructive"], PDO::PARAM_INT);
                        $stmt->bindValue(5, (int)$result["affirmativeQuestions"], PDO::PARAM_INT);
                        $stmt->bindValue(6, (int)$result["affirmativeAnswers"], PDO::PARAM_INT);
                        $stmt->bindValue(7, (int)$result["affirmativeRebuttal1"], PDO::PARAM_INT);
                        $stmt->bindValue(8, (int)$result["affirmativeRebuttal2"], PDO::PARAM_INT);
                        $stmt->bindValue(9, (int)$result["affirmativeManner"], PDO::PARAM_INT);
                        $stmt->bindValue(10, $result["vote"] === "negative" ? 1 : 0, PDO::PARAM_INT);
                        $stmt->bindValue(11, (int)$result["negativeConstructive"], PDO::PARAM_INT);
                        $stmt->bindValue(12, (int)$result["negativeQuestions"], PDO::PARAM_INT);
                        $stmt->bindValue(13, (int)$result["negativeAnswers"], PDO::PARAM_INT);
                        $stmt->bindValue(14, (int)$result["negativeRebuttal1"], PDO::PARAM_INT);
                        $stmt->bindValue(15, (int)$result["negativeRebuttal2"], PDO::PARAM_INT);
                        $stmt->bindValue(16, (int)$result["negativeManner"], PDO::PARAM_INT);
                        $stmt->execute();
                    }

                    $dbh->commit();
                }
                catch (PDOException $e)
                {
                    $dbh->rollback();

                    http_response_code(400);
                    exit();
                }

                http_response_code(204);

                break;

            # 試合を削除
            case "DELETE":
                authorize();

                echo("DELETE /events/:id/games/:id");

                break;

            case "OPTIONS":
                header("Access-Control-Allow-Methods: GET, PATCH, DELETE, OPTIONS");
                break;

            default:
                http_response_code(405);
        }
    }

    else
    {
        http_response_code(404);
    }


    function authorize()
    {
        $header = getallheaders()["Authorization"];
        if (!$header)
        {
            header("WWW-Authenticate: Bearer realm=\"\"");
            http_response_code(401);
            exit();
        }
        else if (preg_match("#^Bearer (.+)$#", $header, $matches))
        {
            $token = base64_decode($matches[1], true);
            if (!$token)
            {
                header("WWW-Authenticate: Bearer error=\"invalid_token\"");
                http_response_code(401);
                exit();
            }
            else if (preg_match("#^(.+)/(.+)$#", $token, $matches))
            {
                global $dbh;

                $dbh = new PDO(
                    "mysql:host=*************************;dbname=********************;charset=utf8mb4",
                    "****************",
                    "****************",
                    array(
                        PDO::ATTR_DEFAULT_FETCH_MODE=> PDO::FETCH_ASSOC,
                        PDO::ATTR_ERRMODE=> PDO::ERRMODE_EXCEPTION
                    )
                );

                $stmt = $dbh->prepare("
                    SELECT
                        password
                    FROM
                        staffs
                    WHERE
                        name = ?
                    ;
                ");
                $stmt->bindValue(1, $matches[1], PDO::PARAM_STR);
                $stmt->execute();

                $row = $stmt->fetch();
                if (!$row || $row["password"] !== $matches[2])
                {
                    header("WWW-Authenticate: Bearer error=\"invalid_token\"");
                    http_response_code(401);
                    exit();
                }
            }
            else
            {
                header("WWW-Authenticate: Bearer error=\"invalid_token\"");
                http_response_code(401);
                exit();
            }
        }
        else
        {
            header("WWW-Authenticate: Bearer error=\"invalid_request\"");
            http_response_code(400);
            exit();
        }
    }


    function get_path_info()
    {
        if (array_key_exists("", $_SERVER))
        {
            return $_SERVER["PATH_INFO"];
        }

        $script_path = str_replace(basename($_SERVER["SCRIPT_NAME"]), "", $_SERVER["SCRIPT_NAME"]);
        $path_info = str_replace($script_path, "", $_SERVER["REQUEST_URI"]);

        $path_info = strtok($path_info, "?");

        return $path_info;
    }

?>
