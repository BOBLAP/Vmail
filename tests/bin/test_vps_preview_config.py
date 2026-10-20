import importlib.util
import json
from pathlib import Path
import unittest

spec = importlib.util.spec_from_file_location("vps_preview_config", Path(__file__).resolve().parents[2] / "bin/vps-preview-config.py")
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class PreviewDeploymentTest(unittest.TestCase):
    def test_preserves_all_web_networks_and_only_adds_preview_settings(self):
        base = {"services": {"php": {"image": "custom:prod", "environment": {"APP_SECRET": "private"}, "labels": {"traefik.http.routers.mail.rule": "Host(`mail.test`)"}, "networks": {"default": None, "proxy": {"aliases": ["mail"]}}}}}
        result = module.preview_override(base, "/stage/compose.gotenberg.yaml")
        self.assertEqual({"default": None, "proxy": {"aliases": ["mail"]}, "document-preview": None}, result["services"]["php"]["networks"])
        self.assertNotIn("private", json.dumps(result))
        self.assertNotIn("labels", result["services"]["php"])
        self.assertEqual({"default": None, "proxy": {"aliases": ["mail"]}}, base["services"]["php"]["networks"])
        self.assertEqual("512m", result["services"]["gotenberg"]["mem_limit"])
        self.assertTrue(result["networks"]["document-preview"]["internal"])

    def test_accepts_list_networks_and_implicit_default(self):
        for networks in [None, ["default", "proxy"]]:
            result = module.preview_override({"services": {"php": {"image": "x", "networks": networks}}}, "/stage/compose.gotenberg.yaml")
            self.assertIn("default", result["services"]["php"]["networks"])

    def test_refuses_unmanaged_gotenberg_and_public_preview_networks(self):
        with self.assertRaises(ValueError):
            module.preview_override({"services": {"php": {"image": "x"}, "gotenberg": {"image": "custom"}}}, "x")
        with self.assertRaises(ValueError):
            module.preview_override({"services": {"php": {"image": "x"}}, "networks": {"document-preview": {"external": True}}}, "x")

    def test_managed_upgrade_keeps_existing_network_membership(self):
        result = module.preview_override({"services": {"php": {"image": "x", "networks": {"proxy": None, "document-preview": None}}, "gotenberg": {}}, "networks": {"document-preview": {"internal": True}}}, "new", True)
        self.assertEqual({"proxy", "document-preview"}, set(result["services"]["php"]["networks"]))

if __name__ == "__main__":
    unittest.main()
