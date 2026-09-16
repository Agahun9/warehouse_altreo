#!/usr/bin/env bash
set -euo pipefail

runtime="${1:-osx-arm64}"
profile="${2:-production}"
if [[ "$runtime" != "osx-arm64" && "$runtime" != "osx-x64" ]]; then
  echo "Użycie: ./scripts/build-macos.sh [osx-arm64|osx-x64]" >&2
  exit 2
fi
if [[ "$profile" != "production" && "$profile" != "sandbox" ]]; then
  echo "Profil musi być równy production albo sandbox." >&2
  exit 2
fi

script_dir="$(cd "$(dirname "$0")" && pwd)"
project_root="$(cd "$script_dir/.." && pwd)"
publish_dir="$project_root/dist/$profile/$runtime/publish"
app_name="Altreo Print Agent"
binary_name="AltreoPrintAgent"
if [[ "$profile" == "sandbox" ]]; then app_name="Altreo Print Agent Sandbox"; binary_name="AltreoPrintAgent-Sandbox"; fi
app_dir="$project_root/dist/$profile/$runtime/$app_name.app"

dotnet publish "$project_root/src/AltreoPrintAgent/AltreoPrintAgent.csproj" \
  -c Release -r "$runtime" --self-contained true -o "$publish_dir" \
  -p:PublishSingleFile=true -p:IncludeNativeLibrariesForSelfExtract=false \
  -p:AgentProfile="$profile" \
  -p:DebugType=None -p:DebugSymbols=false

mkdir -p "$app_dir/Contents/MacOS"
cp "$publish_dir/$binary_name" "$app_dir/Contents/MacOS/AltreoPrintAgent"
shopt -s nullglob
native_libraries=("$publish_dir"/*.dylib)
if (( ${#native_libraries[@]} )); then
  cp "${native_libraries[@]}" "$app_dir/Contents/MacOS/"
fi
cp "$project_root/scripts/Info.plist" "$app_dir/Contents/Info.plist"
if [[ "$profile" == "sandbox" ]]; then
  /usr/libexec/PlistBuddy -c "Set :CFBundleIdentifier pl.altreo.printagent.sandbox" "$app_dir/Contents/Info.plist"
  /usr/libexec/PlistBuddy -c "Set :CFBundleName Altreo Print Agent Sandbox" "$app_dir/Contents/Info.plist"
  /usr/libexec/PlistBuddy -c "Set :CFBundleDisplayName Altreo Print Agent Sandbox" "$app_dir/Contents/Info.plist"
fi
codesign --force --deep --sign - "$app_dir"

echo "Gotowe: $app_dir"
