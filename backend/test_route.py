import heapq
import math
import unittest
from unittest.mock import patch

from backend import route


class AStarRouteTests(unittest.TestCase):
    ROUTE_PAIRS = (
        (18, 47),
        (18, 88),
        (20, 88),
        (47, 88),
        (275, 18),
    )

    @classmethod
    def setUpClass(cls):
        cls.node_rows, cls.edge_rows = route.load_graph()
        cls.nodes = {int(node['id']): node for node in cls.node_rows}
        cls.hierarchy = route.hierarchy_ids(cls.nodes)

    def dijkstra_cost(self, start_id, end_id, step_free):
        adjacency = {node_id: [] for node_id in self.nodes}

        for edge in self.edge_rows:
            kind = route.edge_kind(edge.get('edge_type_name'))

            # Current mapnev labels ordinary hallways "Standard Hallway".
            # route.edge_kind classifies hallways as passages, so they remain
            # available as step-free connections by default.
            if step_free and kind not in ('lift', 'passage'):
                continue

            first_id = int(edge['node1'])
            second_id = int(edge['node2'])
            if first_id not in self.nodes or second_id not in self.nodes:
                continue

            first_floor, first_building = self.hierarchy[first_id]
            second_floor, second_building = self.hierarchy[second_id]
            kind_penalty = {
                'escalator': route.ESCALATOR_PENALTY,
                'stair': route.STAIR_PENALTY,
            }.get(kind, 0.0)
            edge_cost = (
                float(edge['weight'])
                + kind_penalty
                + (route.FLOOR_WEIGHT if first_floor != second_floor else 0.0)
                + (route.BUILDING_WEIGHT if first_building != second_building else 0.0)
            )
            adjacency[first_id].append((second_id, edge_cost))
            adjacency[second_id].append((first_id, edge_cost))

        distances = {start_id: 0.0}
        queue = [(0.0, start_id)]

        while queue:
            current_cost, current_id = heapq.heappop(queue)
            if current_cost > distances.get(current_id, math.inf):
                continue
            if current_id == end_id:
                return current_cost

            for neighbor_id, edge_cost in adjacency[current_id]:
                candidate_cost = current_cost + edge_cost
                if candidate_cost < distances.get(neighbor_id, math.inf):
                    distances[neighbor_id] = candidate_cost
                    heapq.heappush(queue, (candidate_cost, neighbor_id))

        return None

    def run_route_cases(self, step_free):
        for start_id, end_id in self.ROUTE_PAIRS:
            with self.subTest(
                start=start_id,
                end=end_id,
                step_free=step_free,
            ):
                expected_cost = self.dijkstra_cost(start_id, end_id, step_free)
                with patch.object(
                    route,
                    'load_graph',
                    return_value=(self.node_rows, self.edge_rows),
                ):
                    result = route.astar(start_id, end_id, step_free)

                self.assertIsNotNone(
                    result,
                    'Expected test route %s -> %s to be connected' % (start_id, end_id),
                )
                self.assertIsNotNone(expected_cost)
                self.assertAlmostEqual(result['total_weight'], expected_cost)

                if step_free:
                    for edge in result['edges']:
                        edge_name = str(edge.get('edge_type_name') or '').casefold()
                        self.assertNotIn('stair', edge_name)
                        self.assertNotIn('escal', edge_name)
                        self.assertNotIn('escul', edge_name)

    def test_standard_mode_matches_dijkstra(self):
        self.run_route_cases(step_free=False)

    def test_step_free_mode_matches_dijkstra_and_excludes_stairs_and_escalators(self):
        self.run_route_cases(step_free=True)

    def test_current_edge_type_names_support_step_free_classification(self):
        self.assertEqual(route.edge_kind('Standard Hallway'), 'passage')
        self.assertEqual(route.edge_kind('Stairs'), 'stair')
        self.assertEqual(route.edge_kind('Esculator'), 'escalator')
        self.assertEqual(route.edge_kind('Lift'), 'lift')


if __name__ == '__main__':
    unittest.main()
