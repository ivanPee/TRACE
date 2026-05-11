import React from 'react';
import { RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useAppShell } from '../navigation/AppShellContext';
import { colors } from '../theme/colors';

export default function Screen({ children, scroll = true, style, bottomBar, refreshing = false, onRefresh }) {
  const { isInAppShell } = useAppShell();
  const content = scroll ? (
    <ScrollView
      contentContainerStyle={[styles.content, style]}
      showsVerticalScrollIndicator={false}
      refreshControl={onRefresh ? <RefreshControl refreshing={refreshing} onRefresh={onRefresh} /> : undefined}
    >
      {children}
    </ScrollView>
  ) : (
    <View style={[styles.content, styles.fill, style]}>{children}</View>
  );

  return (
    <SafeAreaView edges={isInAppShell ? ['top', 'left', 'right'] : ['top', 'bottom', 'left', 'right']} style={styles.safeArea}>
      <View style={styles.container}>
        {content}
        {!isInAppShell && bottomBar ? <View>{bottomBar}</View> : null}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: colors.paper,
  },
  container: {
    flex: 1,
  },
  content: {
    padding: 20,
  },
  fill: {
    flex: 1,
  },
});
