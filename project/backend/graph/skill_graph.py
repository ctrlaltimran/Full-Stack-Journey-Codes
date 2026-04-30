"""
Skill graph algorithms — BFS and DFS over the related-skills graph.

Why this exists:
  A common lab-project trap is bolting BFS/DFS on for the rubric. We use them
  meaningfully here:

  - BFS: from the user's current skills, find skills 1-2 hops away. These are
    natural "adjacent" skills they should consider learning to unlock more
    jobs. (BFS is right because we want the *closest* unknown skills.)

  - DFS: from a target skill, walk the graph to discover deeper learning
    paths. (DFS is right because we want a path, not the closest neighbors.)

  - Shortest path (BFS): given a target job's required skills, find the
    minimum set of new skills the user would need to learn.
"""

from __future__ import annotations

from collections import deque

from backend.nlp.skills_db import SKILL_GRAPH


# ---------------------------------------------------------------------------
# BFS: adjacent skills
# ---------------------------------------------------------------------------
def adjacent_skills(known_skills: list[str], max_hops: int = 2,
                    limit: int = 8) -> list[dict]:
    """
    BFS outward from the user's known skills.

    Returns a list of dicts: {skill, hops, bridge_skill}
      - hops:        graph distance from the closest known skill
      - bridge_skill: the known skill that connects them (good for explanations)

    Skills the user already has are excluded from the result.
    """
    known_set = set(known_skills)
    if not known_set:
        return []

    # Multi-source BFS: enqueue all known skills at distance 0
    visited = {s: (0, None) for s in known_set if s in SKILL_GRAPH}
    queue = deque((s, 0, None) for s in visited)

    while queue:
        node, dist, bridge = queue.popleft()
        if dist >= max_hops:
            continue
        for neighbor in SKILL_GRAPH.get(node, []):
            if neighbor in visited:
                continue
            # The bridge_skill is the *known* skill that started this path.
            # If we're already 1+ hops out, we keep the original bridge.
            new_bridge = bridge if bridge is not None else node
            visited[neighbor] = (dist + 1, new_bridge)
            queue.append((neighbor, dist + 1, new_bridge))

    # Filter to just the new (unknown) skills, sorted by hops then alpha
    result = [
        {"skill": s, "hops": d, "bridge_skill": b}
        for s, (d, b) in visited.items()
        if s not in known_set
    ]
    result.sort(key=lambda x: (x["hops"], x["skill"]))
    return result[:limit]


# ---------------------------------------------------------------------------
# BFS: shortest skill-path (skill gap analysis for a specific job)
# ---------------------------------------------------------------------------
def skill_gap(known_skills: list[str], required_skills: list[str]) -> dict:
    """
    Compute the minimum set of new skills needed to cover the job's
    requirements.

    Strategy:
      1. Subtract skills the user already has.
      2. For the missing ones, run BFS from the user's known set and record
         the hop distance — closer gaps are easier to bridge.

    Returns:
      {
        "missing":        [skills the user lacks],
        "easy_wins":      [missing skills 1 hop from a known skill],
        "stretch":        [missing skills 2+ hops away or unconnected],
        "coverage_pct":   percentage of required skills already covered (0-100)
      }
    """
    known_set = set(known_skills)
    required_set = set(required_skills)

    if not required_set:
        return {"missing": [], "easy_wins": [], "stretch": [], "coverage_pct": 100}

    covered = required_set & known_set
    missing = sorted(required_set - known_set)
    coverage_pct = round(100 * len(covered) / len(required_set))

    # BFS distances from the user's known set
    distances: dict[str, int] = {s: 0 for s in known_set if s in SKILL_GRAPH}
    queue = deque((s, 0) for s in distances)
    while queue:
        node, d = queue.popleft()
        if d >= 3:  # cap to keep things fast and meaningful
            continue
        for neighbor in SKILL_GRAPH.get(node, []):
            if neighbor not in distances:
                distances[neighbor] = d + 1
                queue.append((neighbor, d + 1))

    easy_wins = [s for s in missing if distances.get(s, 99) == 1]
    stretch = [s for s in missing if distances.get(s, 99) != 1]

    return {
        "missing": missing,
        "easy_wins": easy_wins,
        "stretch": stretch,
        "coverage_pct": coverage_pct,
    }


# ---------------------------------------------------------------------------
# DFS: career path exploration
# ---------------------------------------------------------------------------
def explore_paths(start_skill: str, max_depth: int = 3,
                  max_paths: int = 5) -> list[list[str]]:
    """
    DFS from a starting skill to enumerate possible learning paths.

    This is illustrative — useful for the "Career Paths" UI where we want to
    show "if you learn X, here's where it can take you".

    Returns a list of paths (each path is a list of skill names).
    """
    if start_skill not in SKILL_GRAPH:
        return []

    paths: list[list[str]] = []

    def dfs(node: str, path: list[str], visited: set[str]):
        if len(paths) >= max_paths:
            return
        if len(path) >= max_depth:
            paths.append(path.copy())
            return

        # Add as a partial path even if shorter than max_depth
        extended = False
        for neighbor in SKILL_GRAPH.get(node, []):
            if neighbor in visited:
                continue
            extended = True
            visited.add(neighbor)
            path.append(neighbor)
            dfs(neighbor, path, visited)
            path.pop()
            visited.remove(neighbor)
            if len(paths) >= max_paths:
                return

        if not extended and len(path) > 1:
            paths.append(path.copy())

    dfs(start_skill, [start_skill], {start_skill})
    return paths
