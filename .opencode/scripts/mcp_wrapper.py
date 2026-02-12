#!/usr/bin/env python3
"""
MCP Wrapper for Handoff Tool
Exposes handoff functionality as MCP tools
Runs as a persistent MCP server
"""

import json
import os
import subprocess
import sys
from pathlib import Path

# Path to the actual handoff script
HANDOFF_SCRIPT = Path(__file__).parent / "handoff.py"


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
            text=True,
            timeout=30
        )
        return {
            "success": result.returncode == 0,
            "output": result.stdout.strip(),
            "error": result.stderr.strip() if result.stderr else None
        }
    except subprocess.TimeoutExpired:
        return {"success": False, "error": "Command timed out"}
    except Exception as e:
        return {"success": False, "error": str(e)}


def handle_request(request: dict) -> dict:
    """Handle a single JSON-RPC request."""
    method = request.get("method", "")
    params = request.get("params", {})
    
    if method == "handoff.generate":
        return run_handoff("generate", params)
    
    elif method == "handoff.status":
        return run_handoff("status", params)
    
    elif method == "handoff.resume":
        return run_handoff("resume", params)
    
    elif method == "initialize":
        return {
            "jsonrpc": "2.0",
            "id": request.get("id"),
            "result": {
                "protocolVersion": "2024-11-05",
                "capabilities": {},
                "serverInfo": {
                    "name": "handoff",
                    "version": "1.0.0"
                }
            }
        }
    
    elif method == "notifications/initialized":
        return {"jsonrpc": "2.0", "result": None}
    
    elif method == "tools/list":
        return {
            "jsonrpc": "2.0",
            "id": request.get("id"),
            "result": {
                "tools": [
                    {
                        "name": "handoff.generate",
                        "description": "Generate a handoff document",
                        "inputSchema": {
                            "type": "object",
                            "properties": {
                                "path": {"type": "string", "description": "Project directory path"},
                                "task": {"type": "string", "description": "Brief task name"},
                                "completed": {"type": "array", "items": {"type": "string"}, "description": "Completed items"},
                                "next_steps": {"type": "array", "items": {"type": "string"}, "description": "Next steps"},
                                "git": {"type": "boolean", "description": "Include git status"},
                                "commits": {"type": "integer", "description": "Number of recent commits"},
                                "pending": {"type": "boolean", "description": "Include pending items"}
                            },
                            "required": ["path", "task"]
                        }
                    },
                    {
                        "name": "handoff.status",
                        "description": "Check handoff status",
                        "inputSchema": {
                            "type": "object",
                            "properties": {
                                "path": {"type": "string", "description": "Project directory path"}
                            },
                            "required": ["path"]
                        }
                    },
                    {
                        "name": "handoff.resume",
                        "description": "Resume from a handoff document",
                        "inputSchema": {
                            "type": "object",
                            "properties": {
                                "path": {"type": "string", "description": "Project directory path"}
                            },
                            "required": ["path"]
                        }
                    }
                ]
            }
        }
    
    else:
        return {
            "jsonrpc": "2.0",
            "id": request.get("id"),
            "error": {"code": -32601, "message": f"Unknown method: {method}"}
        }


def main():
    """Main MCP server loop."""
    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
            
        try:
            request = json.loads(line)
            response = handle_request(request)
            
            print(json.dumps(response))
            sys.stdout.flush()
            
        except json.JSONDecodeError:
            error = {"jsonrpc": "2.0", "error": {"code": -32700, "message": "Parse error"}}
            print(json.dumps(error))
            sys.stdout.flush()


if __name__ == "__main__":
    main()
