#!/usr/bin/env python3
"""
Handoff Tool - Session and Agent Handoff Generator

Inspired by Claude Code's /handoff pattern from pro-workflow
"""

import argparse
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

# Try to import colorama for colored output
try:
    from colorama import Fore, Style, init
    init(autoreset=True)
    HAS_COLORS = True
except ImportError:
    HAS_COLORS = False
    class Fore:
        RED = GREEN = YELLOW = BLUE = CYAN = RESET = ""
    class Style:
        RESET_ALL = ""

DEFAULT_OUTPUT_DIR = "handoffs"
TEMPLATE_FILE = Path(__file__).parent / "handoff-template.md"


def colorize(text: str, color: str) -> str:
    """Add color to output if available."""
    if not HAS_COLORS:
        return text
    color_map = {
        'red': Fore.RED,
        'green': Fore.GREEN,
        'yellow': Fore.YELLOW,
        'blue': Fore.BLUE,
        'cyan': Fore.CYAN,
    }
    return f"{color_map.get(color, '')}{text}{Style.RESET_ALL}"


def get_git_status(path: Path) -> dict:
    """Get git status information."""
    result = {
        'branch': 'unknown',
        'modified': [],
        'staged': [],
        'untracked': [],
        'commits': []
    }
    
    try:
        os.chdir(path)
        
        # Get current branch
        import subprocess
        branch_result = subprocess.run(
            ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
            capture_output=True, text=True
        )
        if branch_result.returncode == 0:
            result['branch'] = branch_result.stdout.strip()
        
        # Get modified files
        mod_result = subprocess.run(
            ['git', 'status', '--porcelain'],
            capture_output=True, text=True
        )
        if mod_result.returncode == 0:
            for line in mod_result.stdout.strip().split('\n'):
                if line:
                    status = line[:2]
                    filename = line[3:].strip()
                    if 'M' in status:
                        result['modified'].append(filename)
                    elif 'A' in status:
                        result['staged'].append(filename)
                    elif '?' in status:
                        result['untracked'].append(filename)
        
        # Get recent commits
        log_result = subprocess.run(
            ['git', 'log', '--oneline', '-10'],
            capture_output=True, text=True
        )
        if log_result.returncode == 0:
            result['commits'] = [
                line.strip() for line in log_result.stdout.strip().split('\n')
                if line.strip()
            ]
            
    except Exception as e:
        result['error'] = str(e)
    
    return result


def get_current_files(path: Path, extensions: Optional[list] = None) -> list:
    """Get recently modified files."""
    if extensions is None:
        extensions = ['.php', '.js', '.ts', '.vue', '.json', '.md', '.xml', '.yaml', '.yml']
    
    files = []
    try:
        # Sort by modification time, most recent first
        for f in sorted(path.rglob('*'), key=lambda x: x.stat().st_mtime if x.is_file() else 0, reverse=True)[:20]:
            if f.is_file() and any(f.suffix in extensions for f in [f.suffix] if f.suffix):
                files.append(str(f.relative_to(path)))
    except Exception:
        pass
    
    return files


def generate_handoff(
    path: Path,
    output_file: Optional[Path] = None,
    include_git: bool = False,
    include_commits: int = 0,
    include_pending: bool = False,
    task_name: Optional[str] = None,
    author: str = "User",
    executive_summary: Optional[str] = None,
    completed: Optional[list] = None,
    in_progress: Optional[list] = None,
    pending: Optional[list] = None,
    next_steps: Optional[list] = None
) -> Path:
    """Generate a handoff document."""
    
    timestamp = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M")
    date_only = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    
    # Get task name from directory if not provided
    if task_name is None:
        task_name = path.name.replace('-', ' ').replace('_', ' ').title()
    
    # Generate output filename
    if output_file is None:
        output_dir = path / DEFAULT_OUTPUT_DIR
        output_dir.mkdir(exist_ok=True)
        output_file = output_dir / f"HANDOFF_{date_only}.md"
    
    # Collect git info
    git_info = {}
    if include_git:
        git_info = get_git_status(path)
    
    # Get recent files
    recent_files = get_current_files(path)
    
    # Build the handoff document
    handoff = f"""# Handoff: {task_name}

**Date:** {timestamp}
**Project:** {path.absolute()}
**Author:** {author}

---

## Executive Summary

{executive_summary or "Work in progress. Document created during session handoff."}

---

## Task State

### Completed ✓
"""
    
    if completed:
        for item in completed:
            handoff += f"- [x] {item}\n"
    else:
        handoff += "- [ ] No completed items documented\n"
    
    handoff += """
### In Progress 🔄
"""
    
    if in_progress:
        for item in in_progress:
            handoff += f"- 🔄 {item}\n"
    else:
        handoff += "- No items currently in progress\n"
    
    handoff += """
### Pending ⏳
"""
    
    if pending:
        for item in pending:
            handoff += f"- [ ] {item}\n"
    elif include_pending:
        handoff += "- [ ] No pending items identified\n"
    
    # Git status section
    if git_info:
        handoff += f"""
---

## Git Status

**Branch:** {git_info.get('branch', 'unknown')}

### Modified Files
```
"""
        if git_info.get('modified'):
            for f in git_info['modified']:
                handoff += f"modified: {f}\n"
        else:
            handoff += "No modified files\n"
        
        handoff += """```

### Staged Files
```
"""
        if git_info.get('staged'):
            for f in git_info['staged']:
                handoff += f"staged: {f}\n"
        else:
            handoff += "No staged files\n"
        
        handoff += """```

### Untracked Files
```
"""
        if git_info.get('untracked'):
            for f in git_info['untracked']:
                handoff += f"new:      {f}\n"
        else:
            handoff += "No untracked files\n"
        handoff += "```"
        
        if include_commits > 0 and git_info.get('commits'):
            handoff += f"""
### Recent Commits
```
"""
            for commit in git_info['commits'][:include_commits]:
                handoff += f"{commit}\n"
            handoff += "```"
    
    # Recent files section
    if recent_files:
        handoff += f"""
---

## Recently Modified Files

```
"""
        for f in recent_files[:15]:
            handoff += f"{f}\n"
        handoff += "```"
    
    # Next steps section
    if next_steps:
        handoff += """
---

## Next Steps

"""
        for i, step in enumerate(next_steps, 1):
            handoff += f"{i}. [ ] {step}\n"
    
    handoff += f"""

---

## Session Metadata

- **Generated:** {timestamp}
- **Project Path:** {path.absolute()}

---

*Generated by handoff-tool*
"""
    
    # Write the handoff document
    output_file.write_text(handoff)
    
    print(colorize(f"✓ Handoff document created: {output_file}", 'green'))
    return output_file


def resume_handoff(handoff_file: Path, show_status: bool = False) -> dict:
    """Resume work from a handoff document."""
    
    if not handoff_file.exists():
        print(colorize(f"✗ Handoff file not found: {handoff_file}", 'red'))
        sys.exit(1)
    
    content = handoff_file.read_text()
    
    print(colorize(f"\n📋 Resuming from: {handoff_file.name}", 'cyan'))
    print("=" * 50)
    
    # Extract key sections
    lines = content.split('\n')
    in_section = None
    sections = {}
    current_content = []
    
    for line in lines:
        if line.startswith('## '):
            if current_content:
                sections[in_section] = '\n'.join(current_content)
            in_section = line[3:].strip()
            current_content = []
        elif line.startswith('---'):
            continue
        elif in_section:
            current_content.append(line)
    
    if in_section and current_content:
        sections[in_section] = '\n'.join(current_content)
    
    # Print summary
    if 'Executive Summary' in sections:
        print(colorize("\n📝 Executive Summary:", 'yellow'))
        print(sections['Executive Summary'].strip())
    
    if 'Task State' in sections:
        print(colorize("\n✅ Task State:", 'yellow'))
        print(sections['Task State'].strip())
    
    if 'Next Steps' in sections:
        print(colorize("\n👉 Next Steps:", 'yellow'))
        print(sections['Next Steps'].strip())
    
    if show_status:
        print(colorize("\n📊 Checking current status...", 'blue'))
        # Could integrate with git to show current state
    
    return sections


def check_status(path: Path) -> dict:
    """Check handoff status for a project."""
    
    handoff_dir = path / DEFAULT_OUTPUT_DIR
    status = {
        'handoffs': [],
        'last_modified': None,
        'pending_count': 0
    }
    
    if handoff_dir.exists():
        handoffs = sorted(handoff_dir.glob("HANDOFF_*.md"), key=lambda x: x.stat().st_mtime, reverse=True)
        status['handoffs'] = [h.name for h in handoffs[:10]]
        if handoffs:
            status['last_modified'] = handoffs[0].stat().st_mtime
    
    return status


def show_template():
    """Show the default handoff template."""
    print("""
# Handoff: [Task Name]

**Date:** YYYY-MM-DD HH:MM TZ
**Origin:** [Session/Agent name]
**Author:** [Who created this]

## Executive Summary

Brief overview of what was accomplished and current state.

## Task State

### Completed ✓
- [x] Item completed
- [x] Another completed item

### In Progress 🔄
- Item currently being worked on
- Current focus area

### Pending ⏳
- [ ] Task waiting on dependencies
- [ ] Future work identified

## Git Status

**Branch:** branch-name

### Modified Files
```
modified: src/Auth.php
new:      tests/AuthTest.php
```

### Recent Commits
```
abc1234 Add user authentication
def5678 Fix password validation
```

## Next Steps

1. [ ] Next action item
2. [ ] Follow-up task

## Session Metadata

- **Duration:** HH:MM
- **Model Used:** model-name

---

*Generated by handoff-tool*
""")


def main():
    parser = argparse.ArgumentParser(
        description="Session and Agent Handoff Tool",
        formatter_class=argparse.RawDescriptionHelpFormatter
    )
    
    subparsers = parser.add_subparsers(dest='command', help='Commands')
    
    # Generate command
    gen_parser = subparsers.add_parser('generate', help='Generate handoff document')
    gen_parser.add_argument('--path', '-p', default='.', help='Project path')
    gen_parser.add_argument('--output', '-o', help='Output file path')
    gen_parser.add_argument('--git', '-g', action='store_true', help='Include git status')
    gen_parser.add_argument('--commits', '-c', type=int, default=0, help='Number of recent commits to include')
    gen_parser.add_argument('--pending', action='store_true', help='Include pending items section')
    gen_parser.add_argument('--task', '-t', help='Task name')
    gen_parser.add_argument('--author', '-a', default='User', help='Author name')
    gen_parser.add_argument('--summary', '-s', help='Executive summary')
    gen_parser.add_argument('--completed', nargs='+', help='Completed items')
    gen_parser.add_argument('--in-progress', nargs='+', help='In-progress items')
    gen_parser.add_argument('--pending-items', nargs='+', help='Pending items')
    gen_parser.add_argument('--next-steps', nargs='+', help='Next steps')
    
    # Resume command
    resume_parser = subparsers.add_parser('resume', help='Resume from handoff document')
    resume_parser.add_argument('--file', '-f', required=True, help='Handoff file path')
    resume_parser.add_argument('--show-status', action='store_true', help='Show current status')
    
    # Status command
    status_parser = subparsers.add_parser('status', help='Check handoff status')
    status_parser.add_argument('--path', '-p', default='.', help='Project path')
    
    # Template command
    subparsers.add_parser('template', help='Show handoff template')
    
    # Interactive command
    subparsers.add_parser('interactive', help='Interactive handoff creation')
    
    args = parser.parse_args()
    
    if args.command == 'generate':
        output = generate_handoff(
            path=Path(args.path),
            output_file=Path(args.output) if args.output else None,
            include_git=args.git,
            include_commits=args.commits,
            include_pending=args.pending,
            task_name=args.task,
            author=args.author,
            executive_summary=args.summary,
            completed=args.completed,
            in_progress=args.in_progress,
            pending=args.pending_items,
            next_steps=args.next_steps
        )
        print(colorize(f"\n📄 Handoff saved to: {output}", 'cyan'))
        
    elif args.command == 'resume':
        resume_handoff(Path(args.file), show_status=args.show_status)
        
    elif args.command == 'status':
        status = check_status(Path(args.path))
        print(colorize("\n📊 Handoff Status", 'yellow'))
        print("=" * 30)
        print(f"Handoffs directory: {DEFAULT_OUTPUT_DIR}")
        print(f"Recent handoffs: {len(status['handoffs'])}")
        if status['handoffs']:
            print("\nRecent handoffs:")
            for h in status['handoffs'][:5]:
                print(f"  - {h}")
    
    elif args.command == 'template':
        show_template()
    
    elif args.command == 'interactive':
        print(colorize("\n🗣️  Interactive Handoff Creation", 'cyan'))
        print("=" * 40)
        
        task_name = input("Task name: ").strip() or "Session Handoff"
        author = input("Author (Enter for 'User'): ").strip() or "User"
        summary = input("\nExecutive summary: ").strip()
        
        print("\nCompleted items (empty line to finish):")
        completed = []
        while True:
            line = input("  - ").strip()
            if not line:
                break
            completed.append(line)
        
        print("\nIn-progress items (empty line to finish):")
        in_progress = []
        while True:
            line = input("  - ").strip()
            if not line:
                break
            in_progress.append(line)
        
        print("\nPending items (empty line to finish):")
        pending = []
        while True:
            line = input("  - ").strip()
            if not line:
                break
            pending.append(line)
        
        print("\nNext steps (empty line to finish):")
        next_steps = []
        while True:
            line = input("  - ").strip()
            if not line:
                break
            next_steps.append(line)
        
        include_git = input("\nInclude git status? (y/n): ").strip().lower() == 'y'
        include_commits = 5 if include_git else 0
        if include_git:
            try:
                include_commits = int(input("Number of recent commits: ").strip()) or 5
            except ValueError:
                include_commits = 5
        
        generate_handoff(
            path=Path('.'),
            include_git=include_git,
            include_commits=include_commits,
            task_name=task_name,
            author=author,
            executive_summary=summary or None,
            completed=completed or None,
            in_progress=in_progress or None,
            pending=pending or None,
            next_steps=next_steps or None
        )
    
    else:
        parser.print_help()


if __name__ == "__main__":
    main()
