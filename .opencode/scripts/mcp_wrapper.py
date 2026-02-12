#!/usr/bin/env python3
"""
MCP Wrapper for Handoff Tool
Exposes handoff functionality as MCP tools
"""

import json
import os
import subprocess
import sys
from pathlib import Path

# Path to the actual handoff script
HANDOFF_SCRIPT = Path(__file__).parent / "handoff.py"


def handle_request():
    """Handle MCP request from OpenCode."""
    try:
        # Read JSON-RPC request from stdin
        request = json.load(sys.stdin)
        
        method = request.get("method", "")
        params = request.get("params", {})
        
        if method == "handoff.generate":
            result = run_handoff("generate", params)
            return {"result": result}
        
        elif method == "handoff.status":
            result = run_handoff("status", params)
            return {"result": result}
        
        elif method == "handoff.resume":
            result = run_handoff("resume", params)
            return {"result": result}
        
        else:
            return {"error": f"Unknown method: {method}"}
            
    except Exception as e:
        return {"error": str(e)}


def run_handoff(command: str, params: dict) -> dict:
    """Run handoff command and return result."""
    os.chdir(params.get("path", "."))
    
    args = [str(HANDOFF_SCRIPT), command]
    
    if command == "generate":
        if params.get("git"):
            args.extend(["--git"])
        if params.get("commits"):
            args.extend(["--commits", str(params["commits"])])
        if params.get("pending"):
            args.append("--pending")
    
    try:
        result = subprocess.run(
            args,
            capture_output=True,
            text=True
        )
        return {
            "success": result.returncode == 0,
            "output": result.stdout,
            "error": result.stderr
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


if __name__ == "__main__":
    response = handle_request()
    print(json.dumps(response))
