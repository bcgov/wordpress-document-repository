import { defaultTheme } from "@vuepress/theme-default";
import { defineUserConfig } from "vuepress";
import { searchPlugin } from "@vuepress/plugin-search";
import { viteBundler } from "@vuepress/bundler-vite";

export default defineUserConfig({
  base: "/wordpress-document-repository/",
  lang: "en-US",
  title: "WordPress Document Repository",
  description:
    "A WordPress plugin to manage and display documents stored in a repository.",
  bundler: viteBundler(),
  theme: defaultTheme({
    logo: "/images/BCID_H_rgb_pos.png",
    logoDark: "/images/BCID_H_rgb_rev.png",
    editLink: false,
    lastUpdated: false,
    repo: "bcgov/wordpress-document-repository",
    repoLabel: "Github",
    sidebarDepth: 2,
    navbar: [
      {
        text: "Home",
        link: "/",
      },
    ],
    sidebar: [
      {
        text: "Site Editor",
        collapsible: true,
        children: [
          {
            text: "Metadata Settings",
            link: "/guide/SiteEditor/metadata-settings",
          },
          {
            text: "Document Repository Feature",
            link: "/guide/SiteEditor/document-repository-feature",
          },
          {
            text: "Public Display Feature",
            link: "/guide/SiteEditor/public-display-feature",
          },
        ],
      },
    ],
  }),
  plugins: [
    searchPlugin({
      locales: {
        "/": {
          placeholder: "Search...",
        },
      },
    }),
  ],
});
