#!/usr/bin/env node

/**
 * MuseDock MCP Server — Auto-Discovery
 *
 * This server fetches available tools from the MuseDock API (/api/v1/tools)
 * and dynamically registers them as MCP tools. When plugins add new API
 * endpoints, the MCP Server picks them up automatically.
 *
 * Environment variables:
 *   MUSEDOCK_API_URL  — Base URL (e.g. https://musedock.com)
 *   MUSEDOCK_API_KEY  — Bearer API key (mdk_xxx)
 */

import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";

const API_URL = (process.env.MUSEDOCK_API_URL || "https://musedock.com").replace(
  /\/$/,
  ""
);
const API_KEY = process.env.MUSEDOCK_API_KEY || "";

if (!API_KEY) {
  console.error("MUSEDOCK_API_KEY environment variable is required");
  process.exit(1);
}

// ---------------------------------------------------------------------------
// HTTP helper
// ---------------------------------------------------------------------------
async function apiCall(method, path, body = null) {
  const url = `${API_URL}${path}`;
  const headers = {
    Authorization: `Bearer ${API_KEY}`,
    "Content-Type": "application/json",
    Accept: "application/json",
  };

  const options = { method, headers };
  if (body) options.body = JSON.stringify(body);

  const res = await fetch(url, options);
  const text = await res.text();

  let data;
  try {
    data = JSON.parse(text);
  } catch {
    return {
      success: false,
      error: { code: "PARSE_ERROR", message: text.slice(0, 500) },
    };
  }

  if (!res.ok && !data.error) {
    data.error = { code: `HTTP_${res.status}`, message: res.statusText };
  }
  return data;
}

// ---------------------------------------------------------------------------
// Convert API tool parameter to Zod schema
// ---------------------------------------------------------------------------
function paramToZod(param) {
  let schema;

  switch (param.type) {
    case "number":
    case "integer":
      schema = z.number();
      break;
    case "boolean":
      schema = z.boolean();
      break;
    case "array":
      if (param.items === "number" || param.items === "integer") {
        schema = z.array(z.number());
      } else {
        schema = z.array(z.string());
      }
      break;
    case "string":
    default:
      if (param.enum) {
        schema = z.enum(param.enum);
      } else {
        schema = z.string();
      }
      break;
  }

  if (!param.required) {
    schema = schema.optional();
    if (param.default !== undefined) {
      schema = schema.default(param.default);
    }
  }

  if (param.description) {
    schema = schema.describe(param.description);
  }

  return schema;
}

// ---------------------------------------------------------------------------
// Build the path with path parameters substituted
// ---------------------------------------------------------------------------
function buildPath(pathTemplate, params) {
  let path = pathTemplate;
  for (const [key, value] of Object.entries(params)) {
    path = path.replace(`{${key}}`, encodeURIComponent(value));
  }
  return path;
}

// ---------------------------------------------------------------------------
// Register a single API tool as an MCP tool
// ---------------------------------------------------------------------------
function registerTool(server, tool) {
  const params = tool.parameters || [];

  // Build Zod schema from parameter definitions
  const schemaObj = {};
  for (const param of params) {
    schemaObj[param.name] = paramToZod(param);
  }

  server.tool(tool.name, tool.description || tool.name, schemaObj, async (input) => {
    const method = (tool.method || "GET").toUpperCase();
    let path = tool.path;

    // Separate path params, query params, and body params
    const pathParams = {};
    const queryParams = {};
    const bodyParams = {};

    for (const param of params) {
      const value = input[param.name];
      if (value === undefined || value === null) continue;

      if (param.in === "path") {
        pathParams[param.name] = value;
      } else if (param.in === "query") {
        queryParams[param.name] = value;
      } else {
        bodyParams[param.name] = value;
      }
    }

    // Substitute path parameters
    path = buildPath(path, pathParams);

    // Add query parameters
    const queryParts = [];
    for (const [key, value] of Object.entries(queryParams)) {
      queryParts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
    }
    if (queryParts.length > 0) {
      path += (path.includes("?") ? "&" : "?") + queryParts.join("&");
    }

    // Make the API call
    const body = method === "GET" || method === "DELETE" ? null : bodyParams;
    let data = await apiCall(method, path, body && Object.keys(body).length > 0 ? body : null);

    // Handle confirmation flow: if API requires confirmation and user provided confirm=true
    if (data.confirmation_required && input.confirm) {
      // Resend with confirm: true in body
      const confirmBody = { ...(body || {}), confirm: true };
      data = await apiCall(method, path, confirmBody);
    }

    // Format confirmation warnings clearly for the AI
    if (data.confirmation_required) {
      return {
        content: [{
          type: "text",
          text: `⚠️ CONFIRMATION REQUIRED\n\n${data.warning}\n\n${data.message}\n\nTo proceed, call this tool again with confirm: true`,
        }],
      };
    }

    return {
      content: [{ type: "text", text: JSON.stringify(data, null, 2) }],
    };
  });
}

// ---------------------------------------------------------------------------
// Main: fetch tools and start server
// ---------------------------------------------------------------------------
async function main() {
  // Fetch available tools from the API
  const toolsResponse = await apiCall("GET", "/api/v1/tools");

  if (!toolsResponse.success || !toolsResponse.tools) {
    console.error(
      "Failed to fetch tools from API:",
      JSON.stringify(toolsResponse)
    );
    process.exit(1);
  }

  const tools = toolsResponse.tools;
  console.error(`MuseDock MCP: Discovered ${tools.length} tools`);

  // Create MCP server
  const server = new McpServer({
    name: "musedock",
    version: "1.0.0",
  });

  // Register each tool dynamically
  for (const tool of tools) {
    try {
      registerTool(server, tool);
      console.error(`  - ${tool.name} (${tool.method} ${tool.path})`);
    } catch (err) {
      console.error(`  ! Failed to register ${tool.name}: ${err.message}`);
    }
  }

  // Start stdio transport
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((err) => {
  console.error("MCP Server error:", err);
  process.exit(1);
});
