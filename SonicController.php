<?php declare(strict_types=1);
/**
 * Sonic
 * @copyright 2020 Haunted Bees Productions
 * @author Sean Finch <fench@hauntedbees.com>
 * @license https://www.gnu.org/licenses/agpl-3.0.en.html GNU Affero General Public License
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * 
 * @see https://github.com/HauntedBees/BeeAPI
 */
class SonicController extends BeeController {
    private string $puncregex_sql = "'[-!''.,]'";
    private string $puncregex_php = "/[-!'.,]/";
    public function __construct() { parent::__construct("sonic"); }
    /* #region Auth */
    public function PostLogin(BeeCredentials $credentials) {
        try {
            $auth = new BeeAuth();
            $userInfo = $auth->Login($credentials);
            $token = $auth->GenerateJWTToken($userInfo);
            return $this->response->OK($token);
        } catch(BeeAuthException $e) {
            return $this->response->Error($e->getMessage());
        }
    }
    public function GetCaptcha() {
        $builder = new CaptchaBuilder();
        $builder->build();
        $salt = $this->GetConfigInfo("db_sonic", "captchasalt");
        $phrase = strtr(strtolower($builder->getPhrase()), "01", "ol");
        $token = password_hash($salt.$phrase, PASSWORD_DEFAULT);
        return $this->response->Custom([
            "img" => $builder->inline(),
            "token" => $token
        ]);
    }
    /** @return string */
    public function PostFeedback(SonicPostFeedback $f) {
        $this->AssertRequired($f->captcha);
        if(strlen($f->token) === 0) { return $this->response->Error("Missing CAPTCHA. Please refresh the page and try again."); }
        $salt = $this->GetConfigInfo("db_sonic", "captchasalt");
        $phrase = strtr(strtolower($f->captcha), "01", "ol");
        $token = $salt.$phrase;
        if(!password_verify($token, $f->token)) { return $this->response->Error("Incorrect CAPTCHA. Please try again or refresh the page to load a new one."); }
        
        $this->AssertRequired($f->text);
        $this->AssertLength($f->text, 1000);
        $this->AssertLength($f->name, 69);
        $this->AssertLength($f->contact, 69);
        $this->AssertLength($f->path, 150);
        $this->db->ExecuteNonQuery("INSERT INTO feedback (text, name, contact, path, date, issue) VALUES (:t, :n, :c, :p, NOW(), :i)",
                                    ["t" => $f->text, "n" => $f->name, "c" => $f->contact, "p" => $f->path, "i" => $f->issue]);
        return $this->response->Message("Feedback submitted successfully.");
    }
    /* #endregion */
    /* #region Counts */
    /** @return array */
    public function GetCounts() {
        return $this->response->Custom([
            "entities" => $this->db->GetInt("SELECT COUNT(*) FROM entity"),
            "issues" => $this->db->GetInt("SELECT COUNT(*) FROM issues")
        ]);
    }
    /* #endregion */
    /* #region Issues */
    /** @return SonicIssue[] */
    public function GetCompanyIssues(int $company, bool $showOthers) {
        $relationTypes = $showOthers ? [1, 2, 3] : [1];
        $relSql = implode(", ", $relationTypes);
        $allIssues = $this->GetFullChain("SonicIssue", $company, "
            WITH RECURSIVE allentities AS (
                SELECT e.id, e.name, e.name AS namepath
                FROM entity e
                WHERE e.id IN (:keysStr)
                UNION ALL
                SELECT e.id, e.name,
                    CASE r.relationtype
                        WHEN 1 THEN CONCAT(a.namepath, '|', e.name)
                        WHEN 2 THEN CONCAT(a.namepath, '|>', e.name)
                        WHEN 3 THEN CONCAT(a.namepath, '|[', e.name)
                    END
                    AS namepath
                FROM allentities a
                    INNER JOIN relationships r ON r.parent = a.id AND r.relationtype IN ($relSql)
                    INNER JOIN entity e ON r.child = e.id
            )
            SELECT a.id AS entityId, a.name AS entityName,
                    IFNULL(it.id, 0) AS issueTypeId, it.name AS issueType, it.icon AS issueIcon, it.color AS issueColor,
                    i.id, i.issue, i.sourceurl, i.startdate, i.enddate, i.ongoing, i.contentwarning, it.showOnTop, a.namepath
            FROM allentities a
                LEFT JOIN issues i ON a.id = i.entity
                LEFT JOIN issuetype it ON i.type = it.id
            WHERE i.id IS NOT NULL OR a.id = :source
            ORDER BY CASE
                WHEN a.id = :source THEN 
                    CASE
                        WHEN i.ongoing = 1 THEN NOW() + NOW()
                        WHEN i.enddate IS NOT NULL THEN NOW() + i.enddate
                        ELSE NOW() + i.startdate
                    END
                WHEN i.ongoing = 1 THEN NOW()
                WHEN i.enddate IS NOT NULL THEN i.enddate
                ELSE i.startdate
            END DESC", $relationTypes);
        return $this->response->OK($allIssues);
    }
    /** @return PageSet.SonicIssue */
    public function GetIssuesPage(array $types, int $offset, int $pageSize = 15) {
        $this->AssertIntArray($types);
        $fullOffset = $offset * $pageSize;
        $whereClause = "";
        $params = [];
        if(count($types) > 0) {
            $res = $this->db->CreateInClause($types);
            $inClause = $res["inClause"];
            $whereClause = "WHERE it.id IN ($inClause)";
            $params = $res["paramsObj"];
        }
        $tbl = $this->db->GetObjects("SonicIssue", "
            SELECT 
                e.id AS entityId, e.name AS entityName,
                it.id AS issueTypeId, it.name AS issueType, it.icon AS issueIcon, it.color AS issueColor,
                i.id, i.issue, i.sourceurl, i.startdate, i.enddate, i.ongoing, i.contentwarning, '' AS namepath
            FROM issues i
                INNER JOIN issuetype it ON i.type = it.id
                INNER JOIN entity e ON i.entity = e.id
            $whereClause
            ORDER BY CASE
                WHEN i.enddate IS NOT NULL THEN i.enddate
                ELSE i.startdate
            END DESC
            LIMIT $pageSize OFFSET $fullOffset", $params);
        $count = $this->db->GetInt("SELECT COUNT(*) FROM issues i INNER JOIN issuetype it ON i.type = it.id $whereClause", $params);
        return $this->response->PageSet($tbl, $count);
    }
    /* #endregion */
    /* #region Issue Types */
    /** @return SonicIssueType[] */
    public function GetIssueTypes() {
        return $this->response->OK($this->db->GetObjects("SonicIssueType", 
            "SELECT id, name, icon, color, showOnTop FROM issuetype ORDER BY name ASC"));
    }
    /* #endregion */
    /* #region Categories */
    /** @return SonicCategory[] */
    public function GetRootCategories() {
        return $this->response->OK($this->db->GetObjects("SonicCategory",
            "SELECT c.id, c.name, c.icon
            FROM category c
                LEFT JOIN categoryrelationships r ON c.id = r.child
            GROUP BY c.id, c.name, c.icon
            HAVING COUNT(DISTINCT r.parent) = 0
            ORDER BY c.name ASC"));
    }
    /** @return SonicCategory[] */
    public function GetChildCategories(int $category) {
        return $this->response->OK($this->db->GetObjects("SonicCategory",
            "SELECT c.id, c.name, c.icon
            FROM category c
                INNER JOIN categoryrelationships r ON r.child = c.id
            WHERE r.parent = :c
            ORDER BY c.name ASC", ["c" => $category]));
    }
    /* #endregion */
    /* #region Graph */
    /** @return SonicGraphInfo[] */
    public function GetGraphData(int $company, bool $showOthers) {
        $relationTypes = $showOthers ? [1, 2, 3] : [1];
        $relSql = implode(", ", $relationTypes);
        $family = $this->GetFullChain("SonicGraphInfo", $company, "
            WITH RECURSIVE allentities AS (
                SELECT e.id AS parentId, e.name AS parentName, r.child, r.asOfDate,
                    e2.id AS childId, e2.name AS childName, r.relationtype,
                    e.iconx AS parentx, e.icony AS parenty, e.img AS parentimg,
                    e2.iconx AS childx, e2.icony AS childy, e2.img AS childimg
                FROM entity e
                    INNER JOIN relationships r ON r.parent = e.id AND r.relationtype IN ($relSql)
                    INNER JOIN entity e2 ON r.child = e2.id
                WHERE e.id IN (:keysStr)
                UNION ALL
                SELECT a.childId AS parentId, a.childName AS parentName, r.child, r.asOfDate,
                    e.id AS childId, e.name AS childName, r.relationtype,
                    a.childx AS parentx, a.childy AS parenty, a.childimg AS parentimg,
                    e.iconx AS childx, e.icony AS childy, e.img AS childimg
                FROM allentities a
                    INNER JOIN relationships r ON r.parent = a.child AND r.relationtype IN ($relSql)
                    INNER JOIN entity e ON r.child = e.id
                WHERE a.child IS NOT NULL
            )
            SELECT parentId, parentName, childId, childName, asOfDate, 
                parentx, parenty, parentimg, childx, childy, childimg, relationtype,  
                CASE WHEN parentId = :source THEN 1 ELSE 0 END AS me
            FROM allentities a", $relationTypes);
        return $this->response->OK($family);
    }
    /** @return array */
    public function GetFullGraphData() {
        return $this->response->Custom([
            "nodes" => $this->db->GetObjects("SonicNode", "SELECT id, name, img, iconx, icony FROM entity"),
            "links" => $this->db->GetObjects("SonicCompanyLink", "SELECT parent AS source, child AS target, relationtype, asOfDate FROM relationships")
        ]);
    }
    /** @return array */
    public function GetCachedFullGraphData() {
        echo file_get_contents("./bigData.json");
    }
    /* #endregion */
    /* #region Companies */
    /** @return PageSet.SonicCompanyList */
    public function GetCompaniesPage(string $query, int $offset, int $pageSize = 15) {
        $fullOffset = $offset * $pageSize;
        $whereClause = "";
        $params = [];
        if($query !== "") {
            $whereClause = "WHERE e.name LIKE :q OR REGEXP_REPLACE(e.name, $this->puncregex_sql, '') LIKE :qr";
            $params = ["q" => "%$query%", "qr" => "%".preg_replace($this->puncregex_php, "", $query)."%"];
        }
        $tbl = $this->db->GetObjects("SonicCompanyList",
            "SELECT e.name, c.name AS categoryname, c.icon AS categoryicon, e.id, COUNT(DISTINCT i.id) AS issues, COUNT(DISTINCT r.child) AS children
            FROM entity e
                LEFT JOIN category c ON e.type = c.id
                LEFT JOIN issues i ON i.entity = e.id
                LEFT JOIN relationships r ON r.parent = e.id AND r.relationtype IN (1, 2)
            $whereClause
            GROUP BY e.name, c.name, c.icon, e.id
            ORDER BY e.name ASC
            LIMIT $pageSize OFFSET $fullOffset", $params);
        $count = $this->db->GetInt("SELECT COUNT(*) FROM entity e $whereClause", $params);
        return $this->response->PageSet($tbl, $count);
    }
    /** @return PageSet.SonicCompanyList */
    public function GetCompaniesByCategoryPage(int $category, int $offset, int $pageSize = 15) {
        $fullOffset = $offset * $pageSize;
        $params = ["c" => $category];
        $tbl = $this->db->GetObjects("SonicCompanyList", "
            WITH RECURSIVE fullcategories AS (
                SELECT c.id, c.name, c.icon
                FROM category c
                WHERE c.id = :c
                UNION ALL
                SELECT c.id, c.name, c.icon
                FROM fullcategories fc
                    INNER JOIN categoryrelationships r ON r.parent = fc.id
                    INNER JOIN category c ON r.child = c.id
            )
            SELECT e.name, fc.name AS categoryname, fc.icon AS categoryicon, e.id, COUNT(DISTINCT i.id) AS issues, COUNT(DISTINCT r.child) AS children
            FROM entity e
                INNER JOIN fullcategories fc ON e.type = fc.id
                LEFT JOIN issues i ON i.entity = e.id
                LEFT JOIN relationships r ON r.parent = e.id AND r.relationtype IN (1, 2)
            GROUP BY e.name, fc.name, fc.icon, e.id
            ORDER BY e.name ASC
            LIMIT $pageSize OFFSET $fullOffset", $params);
        $count = $this->db->GetInt("
            WITH RECURSIVE fullcategories AS (
                SELECT c.id, c.name, c.icon
                FROM category c
                WHERE c.id = :c
                UNION ALL
                SELECT c.id, c.name, c.icon
                FROM fullcategories fc
                    INNER JOIN categoryrelationships r ON r.parent = fc.id
                    INNER JOIN category c ON r.child = c.id
            )
            SELECT COUNT(DISTINCT e.id)
            FROM entity e
                INNER JOIN fullcategories fc ON e.type = fc.id", $params);
         return $this->response->PageSet($tbl, $count);
    }
    /** @return SonicCompanySearchResult */
    public function GetCompanySearch(string $query) {
        return $this->response->OK($this->db->GetObjects("SonicCompanySearchResult",
            "SELECT DISTINCT e.name, e.id, GROUP_CONCAT(DISTINCT p.name ORDER BY p.name ASC SEPARATOR '/') AS parent
            FROM entity e
                LEFT JOIN synonym s ON e.id = s.entityid
                LEFT JOIN entityancestors a ON e.id = a.entityid
                LEFT JOIN entity p ON a.ancestorid = p.id
            WHERE e.name LIKE :n
                OR s.synonym LIKE :n
                OR REGEXP_REPLACE(e.name, $this->puncregex_sql, '') LIKE :nr
                OR REGEXP_REPLACE(s.synonym, $this->puncregex_sql, '') LIKE :nr
            GROUP BY e.name, e.id
            LIMIT 10", ["n" => "%$query%", "nr" => "%".preg_replace($this->puncregex_php, "", $query)."%"]));
    }
    /** @return string[]+string[]+string[] */
    public function GetAdditionalCompanyInfo(int $companyId) {
        $params = ["id" => $companyId];
        $investors = $this->db->GetStrings(
            "SELECT DISTINCT e.name
            FROM relationships r
                INNER JOIN entity e ON r.parent = e.id
            WHERE r.child = :id AND r.relationtype = 2
            ORDER BY e.name ASC", $params);
        $investments = $this->db->GetStrings(
            "SELECT DISTINCT e.name
            FROM relationships r
                INNER JOIN entity e ON r.child = e.id
            WHERE r.parent = :id AND r.relationtype = 2
            ORDER BY e.name ASC", $params);
        $relationships = $this->db->GetStrings(
            "SELECT DISTINCT e.name
            FROM relationships r
                INNER JOIN entity e ON r.child = e.id OR r.parent = e.id
            WHERE (r.parent = :id OR r.child = :id)
                AND r.relationtype = 3
                AND e.id <> :id
            ORDER BY e.name ASC", $params);
        return $this->response->Custom([
            "investors" => $investors,
            "investments" => $investments,
            "relationships" => $relationships
        ]);
    }
    /** @return SonicCompany */
	public function GetCompany(string $companyName) {
		$company = $this->db->GetObject("SonicCompany", "SELECT 
            e.name, e.type, e.id, e.description, IFNULL(c.name, '') AS typename, e.img, e.iconx, e.icony
            FROM entity e
                LEFT JOIN synonym s ON e.id = s.entityid
                LEFT JOIN category c ON e.type = c.id
            WHERE e.name LIKE :n
                OR s.synonym LIKE :n
            LIMIT 1", ["n" => $companyName]);

        $params = ["i" => $company->id];
        $company->synonyms = $this->db->GetStrings("SELECT synonym FROM synonym WHERE entityid = :i", $params);
        $company->hasAddtlRelationships = $this->db->GetBool("
            WITH RECURSIVE ancestor AS (
                SELECT r.parent AS id, e.name, r.relationtype
                FROM relationships r
                    INNER JOIN entity e ON r.parent = e.id
                WHERE r.child = :i OR r.parent = :i
                UNION ALL
                SELECT ep.id, ep.name, r.relationtype
                FROM ancestor a
                    INNER JOIN relationships r ON r.child = a.id
                    INNER JOIN entity ep ON r.parent = ep.id
            )
            SELECT COUNT(*)
            FROM ancestor
            WHERE relationtype <> 1", $params);
        $parents = $this->db->GetObjects("SonicParentCompany", "
            WITH RECURSIVE ancestor AS (
                SELECT r.parent AS id, e.name, 0 AS depth, r.parent AS chain
                FROM relationships r
                    INNER JOIN entity e ON r.parent = e.id
                WHERE r.child = :i AND r.relationtype = 1
                UNION ALL
                SELECT ep.id, ep.name, a.depth + 1 AS depth, a.chain
                FROM ancestor a
                    INNER JOIN relationships r ON r.child = a.id AND r.relationtype = 1
                    INNER JOIN entity ep ON r.parent = ep.id
            )
            SELECT *
            FROM ancestor
            ORDER BY depth ASC", $params);
        $parentVals = [];
        foreach($parents as $parent) {
            if($parent->depth === 0) {
                $company->parents[] = $parent->id;
                $parentVals[$parent->id] = new SonicCompanyChain($parent->id, $parent->name);
            } else {
                $parentVals[$parent->chain]->rootid = $parent->id;
                $parentVals[$parent->chain]->rootname = $parent->name;
            }
        }

        $company->children = $this->db->GetObjects("SonicChildCompany", "
            SELECT r.child, e.name AS text
            FROM relationships r
                INNER JOIN entity e ON r.child = e.id
            WHERE r.parent = :i AND r.relationtype = 1", $params);

        return $this->response->Custom(["result" => $company, "parentVals" => $parentVals]);
    }
    /* #endregion */
    /* #region Helpers */
    private function GetFullChain(string $objClass, int $companyId, string $query, array $relationTypes = [1]):array {
        $rels = implode(", ", $relationTypes);
        $ancestorCompanies = $this->db->GetInts("
            WITH RECURSIVE ancestor AS (
                SELECT e.id, r2.parent AS toppy
                FROM entity e
                    LEFT JOIN relationships r2 ON r2.child = e.id AND r2.relationtype IN ($rels)
                WHERE e.id = :id
                UNION ALL
                SELECT ep.id, r2.parent AS toppy
                FROM ancestor a
                    INNER JOIN relationships r ON r.child = a.id AND r.relationtype IN ($rels)
                    INNER JOIN entity ep ON r.parent = ep.id
                    LEFT JOIN relationships r2 ON r2.child = ep.id AND r2.relationtype IN ($rels)
            )
            SELECT DISTINCT id
            FROM ancestor
            WHERE toppy IS NULL", ["id" => $companyId]);
        if(count($ancestorCompanies) === 0) { throw new Exception("Company not found."); }
        $params = ["source" => $companyId];
        $keysArr = [];
        foreach($ancestorCompanies as $i=>$ac) {
            $params["root$i"] = $ac;
            $keysArr[] = ":root$i";
        }
        $keysStr = implode(", ", $keysArr);
        return $this->db->GetObjects($objClass, str_replace(":keysStr", $keysStr, $query), $params);
    }
    /* #endregion */
}
?>