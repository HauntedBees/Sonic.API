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
class SonicSecureController extends BeeSecureController {
    public function __construct() { parent::__construct("sonic", BEEROLE_ADMIN); }
    public function GetAuth() { $this->response->OK(true); }
    /* #region Feedback */
    /** @return SonicSecureFeedback[] */
    public function GetFeedback() {
        return $this->response->OK($this->db->GetObjects("SonicSecureFeedback",
            "SELECT f.id, f.path, f.text, f.name, f.contact, f.date, i.id AS issue, e.name AS issueParent
            FROM feedback f
				LEFT JOIN issues i ON f.issue = i.id
                LEFT JOIN entity e ON i.entity = e.id
            WHERE f.dismissed = 0
            ORDER BY date DESC"));
    }
    /** @return string */
    public function DeleteFeedback(int $id) {
        $this->db->ExecuteNonQuery("UPDATE feedback SET dismissed = 1 WHERE id = :id", ["id" => $id]);
        return $this->response->Message("Feedback dismissed successfully.");
    }
    /* #endregion */
    /* #region Issues */
    /** @return SonicSecureIssue[] */
    public function GetIssues(int $companyID) {
        return $this->response->OK($this->db->GetObjects("SonicSecureIssue", 
            "SELECT i.id, i.issue, i.type, i.sourceurl, i.contentwarning, i.startdate, i.enddate, i.ongoing
            FROM issues i
                INNER JOIN issuetype it ON i.type = it.id
            WHERE i.entity = :id
            ORDER BY CASE
                WHEN i.ongoing = 1 THEN NOW() + i.startdate
                WHEN i.enddate IS NOT NULL THEN i.enddate
                ELSE i.startdate
            END ASC", ["id" => $companyID]));
    }
    /** @return int */
    public function PostIssue(SonicSecureIssue $i) {
        $params = ["t" => $i->type, "d" => $i->issue, "s"=> $i->sourceurl, "d1" => $i->startdate, "d2" => $i->enddate,
                    "e" => $i->companyid, "cw" => $i->contentwarning, "o" => ($i->ongoing?1:0)];
        if($i->id === 0) {
            $i->id = $this->db->InsertAndReturnID(
                "INSERT INTO issues (entity, type, issue, sourceurl, startdate, enddate, contentwarning, ongoing)
                    VALUES (:e, :t, :d, :s, :d1, :d2, :cw, :o)", $params);
        } else {
            $params["id"] = $i->id;
            unset($params["e"]);
            $this->db->ExecuteNonQuery(
                "UPDATE issues SET type = :t, issue = :d, sourceurl = :s, startdate = :d1, enddate = :d2, contentwarning = :cw, ongoing = :o WHERE id = :id",  $params);
        }
        return $this->response->OK($i->id);
    }
    /** @return string */
    public function DeleteIssue(int $id) {
        $this->db->ExecuteNonQuery("DELETE FROM issues WHERE id = :id", ["id" => $id]);
        return $this->response->Message("Issue deleted successfully.");
    }
    /* #endregion */
    /* #region Issue Types */
    /** @return string */
    public function PostIssueType(SonicIssueType $it) {
        $params = ["n" => $it->name, "i" => $it->icon, "c" => $it->color, "s" => ($it->showOnTop?1:0)];
        if($it->id === 0) {
            $it->id = $this->db->InsertAndReturnID("INSERT INTO issuetype (name, color, icon, showOnTop) VALUES (:n, :c, :i, :s)", $params);
        } else {
            $params["id"] = $it->id;
            $this->db->ExecuteNonQuery("UPDATE issuetype SET name = :n, icon = :i, color = :c, showOnTop = :s WHERE id = :id", $params);
        }
        return $this->response->OK($it->id);
    }
    /* #endregion */
    /* #region Categories */
    /** @return SonicSecureCategory[] */
    public function GetCategories() {
        return $this->response->OK($this->db->GetObjects("SonicSecureCategory",
            "SELECT c.id, c.icon, c.name, COUNT(DISTINCT e.id) count
            FROM category c
				LEFT JOIN entity e ON c.id = e.type
			GROUP BY c.id, c.icon, c.name
            ORDER BY c.name ASC"));
    }
    /** @return int */
    public function GetCategoryParents(int $categoryID) {
        return $this->response->OK($this->db->GetInts("SELECT parent FROM categoryrelationships WHERE child = :id", ["id" => $categoryID]));
    }
    /** @return SonicCategoryNode+SonicLink */
    public function GetFullCategoryGraphData() {
        $nodes = $this->db->GetObjects("SonicCategoryNode",
            "SELECT c.id, c.icon, c.name, COUNT(DISTINCT e.id) count
            FROM category c
				LEFT JOIN entity e ON c.id = e.type
			GROUP BY c.id, c.icon, c.name
            ORDER BY c.name ASC");
        $links = $this->db->GetObjects("SonicLink", "SELECT parent AS source, child AS target FROM categoryrelationships");
        return $this->response->Custom(["nodes" => $nodes, "links" => $links]);
    }
    // UNUSED?
    /** @return BeeLookup[] */
    /*public function GetCategorySearch(string $query) {
        return $this->response->OK($this->db->GetObjects("BeeLookup",
            "SELECT id, name
            FROM category
            WHERE name LIKE :n
            LIMIT 10", ["n" => "%$query%"]));
    }*/
    /** @return int */
    public function PostCategory(SonicCategoryWithParents $cat) {
        $this->AssertIntArray($cat->parents);
        try {
            $this->db->BeginTransaction();
            $params = ["n" => $cat->name, "i" => $cat->icon];
            if($cat->id === 0) {
                $cat->id = $this->db->InsertAndReturnID("INSERT INTO category (icon, name) VALUES (:i, :n)", $params);
            } else {
                $params["id"] = $cat->id;
                $this->db->ExecuteNonQuery("UPDATE category SET name = :n, icon = :i WHERE id = :id", $params);
                $this->db->ExecuteNonQuery("DELETE FROM categoryrelationships WHERE child = :id", ["id" => $cat->id]);
            }
            $this->db->DoMultipleInsert($cat->id, $cat->parents, "INSERT INTO categoryrelationships (child, parent) VALUES ");
            $this->db->CommitTransaction();
            return $this->response->OK($cat->id);
        } catch(Exception $e) {
            $this->db->RollbackTransaction();
            return $this->response->Exception($e);
        }
    }
    /* #endregion */
    /* #region Companies */
    /** @return SonicCompanyList[] */
    public function GetCompanies() { // TODO: should this be paginated??
        return $this->response->OK($this->db->GetObjects("SonicCompanyList",
            "SELECT e.name, c.name AS categoryname, e.id, COUNT(DISTINCT i.id) AS issues, COUNT(DISTINCT r.child) AS children
            FROM entity e
                LEFT JOIN category c ON e.type = c.id
                LEFT JOIN issues i ON i.entity = e.id
                LEFT JOIN relationships r ON r.parent = e.id
            GROUP BY e.name, c.name, e.id
            ORDER BY e.name ASC"));
    }
    /** @return BeeLookup[]+BeeLookup[] */
    public function GetAdditionalCompanyInfo(int $companyId) {
        $params = ["id" => $companyId];
        $investors = $this->db->GetObjects("BeeLookup",
            "SELECT DISTINCT e.id, e.name
            FROM relationships r
                INNER JOIN entity e ON r.parent = e.id
            WHERE r.child = :id AND r.relationtype = 2
            ORDER BY e.name ASC", $params);
        $relationships = $this->db->GetObjects("BeeLookup",
            "SELECT DISTINCT e.name, e.id
            FROM relationships r
                INNER JOIN entity e ON r.child = e.id OR r.parent = e.id
            WHERE (r.parent = :id OR r.child = :id)
                AND r.relationtype = 3
                AND e.id <> :id
            ORDER BY e.name ASC", $params);
        return $this->response->Custom([
            "investors" => $investors,
            "relationships" => $relationships
        ]);
    }
    /** @return int */
    public function PostCompany(SonicSecureCompany $company) {
        $this->AssertIntArray($company->parents);
        $this->AssertIntArray($company->investors);
        $this->AssertIntArray($company->miscrelationships);
        try {
            $this->db->BeginTransaction();
            if($company->newtype !== "") {
                $company->type = $this->db->InsertAndReturnID("INSERT INTO category (name) VALUES (:c)", ["c" => $company->newtype]);
            }
            $params = ["n" => $company->name, "t" => $company->type, "d" => $company->description, "img" => $company->img, "x" => $company->iconx, "y" => $company->icony];
            if($company->id === 0) {
                $existingCount = $this->db->GetInt("SELECT COUNT(*) FROM entity WHERE name = :n", ["n" => $company->name]);
                if($existingCount > 0) { return $this->response->Error("A record with this name already exists."); }
                $company->id = $this->db->InsertAndReturnID("INSERT INTO entity (name, type, description, img, iconx, icony) VALUES (:n, :t, :d, :img, :x, :y)", $params);
            } else {
                $params["id"] = $company->id;
                $this->db->ExecuteNonQuery("UPDATE entity SET name = :n, type = :t, description = :d, img = :img, iconx = :x, icony = :y WHERE id = :id", $params);
                $this->db->ExecuteNonQuery("DELETE FROM synonym WHERE entityid = :i", ["i" => $company->id]);
                $this->db->ExecuteNonQuery("DELETE FROM relationships WHERE child = :i", ["i" => $company->id]);
            }
            $this->db->DoMultipleInsert($company->id, $company->synonyms, "INSERT INTO synonym (entityid, synonym) VALUES ");
            $this->db->DoMultipleInsertTwoPoint($company->id, 1, $company->parents, "INSERT INTO relationships (child, relationtype, parent) VALUES ");
            $this->db->DoMultipleInsertTwoPoint($company->id, 2, $company->investors, "INSERT INTO relationships (child, relationtype, parent) VALUES ");
            $this->db->DoMultipleInsertTwoPoint($company->id, 3, $company->miscrelationships, "INSERT INTO relationships (child, relationtype, parent) VALUES ");
            $this->db->CommitTransaction();
            return $this->response->OK($company->id);
        } catch(Exception $e) {
            $this->db->RollbackTransaction();
            return $this->response->Exception($e);
        }
    }

    /** @return string */
    public function GetRebuildAllAncestors() {
        try {
            $this->db->BeginTransaction();
            $this->db->ExecuteNonQuery("DELETE FROM entityancestors");
            $this->db->ExecuteNonQuery(
                "INSERT INTO entityancestors (entityid, ancestorid)
                SELECT origId, parentId FROM (
                    WITH RECURSIVE ancestor AS (
                        SELECT c.id AS origId, c.name AS origName, c.id AS childId, c.name AS childName, p.id AS parentId, p.name AS parentName, 0 AS depth
                        FROM entity c
                            INNER JOIN relationships r ON r.child = c.id AND r.relationtype = 1
                            INNER JOIN entity p ON r.parent = p.id
                        UNION ALL
                        SELECT a.origId AS origId, a.origName AS origName, a.childId AS childId, a.childName AS childName, ep.id AS parentId, ep.name AS parentName, a.depth + 1 AS depth
                        FROM ancestor a
                            INNER JOIN relationships r ON r.child = a.parentId AND r.relationtype = 1
                            INNER JOIN entity ep ON r.parent = ep.id
                    )
                    SELECT a.origId, a.origName, a.parentId, a.parentName, a.depth
                    FROM ancestor a
                        INNER JOIN (
                            WITH RECURSIVE ancestor AS (
                                SELECT c.id AS origId, c.name AS origName, c.id AS childId, c.name AS childName, p.id AS parentId, p.name AS parentName, 0 AS depth
                                FROM entity c
                                    INNER JOIN relationships r ON r.child = c.id AND r.relationtype = 1
                                    INNER JOIN entity p ON r.parent = p.id
                                UNION ALL
                                SELECT a.origId AS origId, a.origName AS origName, a.childId AS childId, a.childName AS childName, ep.id AS parentId, ep.name AS parentName, a.depth + 1 AS depth
                                FROM ancestor a
                                    INNER JOIN relationships r ON r.child = a.parentId AND r.relationtype = 1
                                    INNER JOIN entity ep ON r.parent = ep.id
                                )
                                SELECT origId, MAX(depth) AS depth FROM ancestor GROUP BY origId
                        ) a2 ON a.origId = a2.origId AND a.depth = a2.depth
                ) v");
            $this->db->CommitTransaction();
            return $this->response->Message("Ancestors rebuilt successfully.");
        } catch(Exception $e) {
            $this->db->RollbackTransaction();
            return $this->response->Exception($e);
        }
    }
    /* #endregion */
}
?>